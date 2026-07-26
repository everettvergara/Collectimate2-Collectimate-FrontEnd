<?php

namespace App\Services\Sms;

use App\Enums\SmsBatchSource;
use App\Enums\SmsReceivedAssociationStatus;
use App\Models\Account;
use App\Models\AccountActivity;
use App\Models\AccountContactInfo;
use App\Models\ActivityType;
use App\Models\SmsCallbackEvent;
use App\Models\SmsDevice;
use App\Models\SmsReceivedMessage;
use App\Models\User;
use App\Services\AccountActivityTotalsSync;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SmsReceivedService
{
    public function __construct(
        protected SmsQueueService $queue,
        protected AccountActivityTotalsSync $activityTotals,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public function persistFromCallback(SmsCallbackEvent $event, ?string $deviceId, array $body): SmsReceivedMessage
    {
        $sender = preg_replace('/\s+/', '', (string) ($body['sender'] ?? '')) ?: '';
        $message = (string) ($body['message'] ?? '');
        $receivedAt = $body['received_at'] ?? $event->event_timestamp ?? now();
        $normalizedSender = $sender !== '' ? $sender : 'unknown';

        $existing = SmsReceivedMessage::query()
            ->where('sms_callback_event_id', $event->id)
            ->first();

        if ($existing) {
            $existing->forceFill([
                'sender' => $normalizedSender,
                'message' => $message,
                'device_id' => $deviceId ?? $existing->device_id,
                'received_at' => $receivedAt,
            ])->save();

            return $existing->fresh(['account', 'contactInfo', 'activity']);
        }

        // Same sender+body often arrives once per device — keep one unmatched, ignore copies.
        $status = $this->recentDuplicateStatus($normalizedSender, $message, $event->id)
            ?? SmsReceivedAssociationStatus::Unmatched;

        $received = SmsReceivedMessage::query()->create([
            'sms_callback_event_id' => $event->id,
            'event_type' => 'SmsReceived',
            'sender' => $normalizedSender,
            'message' => $message,
            'device_id' => $deviceId,
            'received_at' => $receivedAt,
            'association_status' => $status,
        ]);

        if ($status === SmsReceivedAssociationStatus::Ignored) {
            return $received->fresh(['account', 'contactInfo', 'activity']);
        }

        if ($sender === '' || $received->account_id) {
            return $received->fresh(['account', 'contactInfo', 'activity']);
        }

        $contact = $this->findMatchingContact($sender);
        if (! $contact) {
            return $received->fresh(['account', 'contactInfo', 'activity']);
        }

        return $this->associateToContact($received, $contact, SmsReceivedAssociationStatus::Matched);
    }

    public function ignore(SmsReceivedMessage $received): SmsReceivedMessage
    {
        if ($received->account_activity_id) {
            throw ValidationException::withMessages([
                'message' => 'Cannot ignore a message that is already associated to an account activity.',
            ]);
        }

        $received->forceFill([
            'association_status' => SmsReceivedAssociationStatus::Ignored,
            'account_id' => null,
            'account_contact_info_id' => null,
        ])->save();

        return $received->fresh(['account', 'contactInfo', 'activity']);
    }

    public function delete(SmsReceivedMessage $received): void
    {
        if ($received->account_activity_id) {
            throw ValidationException::withMessages([
                'message' => 'Cannot delete a message that is already associated to an account activity.',
            ]);
        }

        $received->delete();
    }

    public function associateToAccount(
        SmsReceivedMessage $received,
        Account $account,
        ?int $contactInfoId = null,
    ): SmsReceivedMessage {
        if ($received->account_id && $received->account_activity_id) {
            throw ValidationException::withMessages([
                'account_id' => 'This message is already associated to an account.',
            ]);
        }

        $contact = null;
        if ($contactInfoId) {
            $contact = AccountContactInfo::query()
                ->withoutGlobalScopes()
                ->where('account_id', $account->id)
                ->whereKey($contactInfoId)
                ->whereIn('type', ['mobile', 'landline'])
                ->first();
        }

        if (! $contact) {
            $contact = $this->findMatchingContact($received->sender, $account->id)
                ?? AccountContactInfo::query()
                    ->withoutGlobalScopes()
                    ->where('account_id', $account->id)
                    ->whereIn('type', ['mobile', 'landline'])
                    ->orderBy('id')
                    ->first();
        }

        return $this->associateToContact($received, $contact, SmsReceivedAssociationStatus::Manual, $account);
    }

    public function reply(
        SmsReceivedMessage $received,
        User $user,
        string $message,
        ?string $runtimeDeviceId = null,
    ): SmsReceivedMessage {
        if (! $received->account_id) {
            throw ValidationException::withMessages([
                'account_id' => 'Associate this message to an account before replying.',
            ]);
        }

        $account = Account::query()->findOrFail($received->account_id);
        $contact = $received->account_contact_info_id
            ? AccountContactInfo::query()->withoutGlobalScopes()->find($received->account_contact_info_id)
            : $this->findMatchingContact($received->sender, $account->id);

        $recipient = $this->queue->resolveRecipientFromContact($contact)
            ?? $this->queue->resolvePrimaryPhone($account)
            ?? preg_replace('/\s+/', '', (string) $received->sender);

        if (! $recipient) {
            throw ValidationException::withMessages([
                'message' => 'No phone/mobile contact available for this account.',
            ]);
        }

        $deviceId = $runtimeDeviceId ?: $received->device_id;
        if ($deviceId) {
            $exists = SmsDevice::query()
                ->where('runtime_device_id', $deviceId)
                ->exists();
            if (! $exists) {
                // Allow raw runtime ids from list-devices even if not in registry yet.
                $deviceId = (string) $deviceId;
            }
        }

        $typeId = ActivityType::query()->where('code', 'sms_send')->value('id');
        if (! $typeId) {
            throw ValidationException::withMessages([
                'message' => 'SMS Send activity type is not configured.',
            ]);
        }

        $activity = $account->activities()->create([
            'occurred_at' => now(),
            'activity_type_id' => $typeId,
            'actor_user_id' => $user->id,
            'agent_profile_id' => $user->agentProfile?->id,
            'assigned_agent_profile_id' => $account->assigned_agent_profile_id,
            'entity_status_id' => $account->entity_status_id,
            'entity_action_code_id' => $account->entity_action_code_id,
            'reference_text' => $message,
            'reference_contact_info_id' => $contact?->id,
            'remarks' => 'Reply to inbound SMS'.($received->device_id ? " (via {$received->device_id})" : ''),
        ]);

        $this->activityTotals->sync($account->fresh());

        $batch = $this->queue->enqueueBatch(
            SmsBatchSource::AccountActivitySingle,
            $user,
            new Collection([[
                'account' => $account,
                'activity' => $activity,
                'message' => $message,
                'recipient' => $recipient,
            ]]),
            $message,
            [
                'reply_to_sms_received_id' => $received->id,
                'preferred_runtime_device_id' => $deviceId,
            ],
            $activity,
        );

        if ($deviceId) {
            $item = $batch->items()->where('account_activity_id', $activity->id)->first();
            $registryId = SmsDevice::query()->where('runtime_device_id', $deviceId)->value('id');
            $item?->forceFill([
                'runtime_device_id' => $deviceId,
                'assigned_sms_device_id' => $registryId,
            ])->save();
        }

        return $received->fresh(['account', 'contactInfo', 'activity']);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapForUi(SmsReceivedMessage $received): array
    {
        $received->loadMissing(['account:id,account_number,account_name', 'activity:id']);

        return [
            'id' => $received->id,
            'event_type' => $received->event_type,
            'sender' => $received->sender,
            'message' => $received->message,
            'message_preview' => $received->message
                ? \Illuminate\Support\Str::limit($received->message, 80)
                : null,
            'device_id' => $received->device_id,
            'received_at' => $received->received_at?->toIso8601String(),
            'association_status' => $received->association_status?->value ?? $received->association_status,
            'account_id' => $received->account_id,
            'account_number' => $received->account?->account_number,
            'account_name' => $received->account?->account_name,
            'account_activity_id' => $received->account_activity_id,
            'can_associate' => ! $received->account_id
                && $received->association_status !== SmsReceivedAssociationStatus::Ignored,
            'can_reply' => (bool) $received->account_id,
            'can_ignore' => ! $received->account_activity_id
                && $received->association_status !== SmsReceivedAssociationStatus::Ignored,
            'can_delete' => ! $received->account_activity_id,
        ];
    }

    protected function recentDuplicateStatus(
        string $sender,
        string $message,
        int $exceptEventId,
    ): ?SmsReceivedAssociationStatus {
        $existing = SmsReceivedMessage::query()
            ->where('sender', $sender)
            ->where('message', $message)
            ->where(function ($q) use ($exceptEventId) {
                $q->whereNull('sms_callback_event_id')
                    ->orWhere('sms_callback_event_id', '!=', $exceptEventId);
            })
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->first();

        if (! $existing) {
            return null;
        }

        // Duplicate of ignored promo, or second copy of the same unmatched inbound.
        return SmsReceivedAssociationStatus::Ignored;
    }

    protected function associateToContact(
        SmsReceivedMessage $received,
        ?AccountContactInfo $contact,
        SmsReceivedAssociationStatus $status,
        ?Account $account = null,
    ): SmsReceivedMessage {
        $account ??= $contact
            ? Account::query()->withoutGlobalScopes()->find($contact->account_id)
            : null;

        if (! $account) {
            throw ValidationException::withMessages([
                'account_id' => 'Account not found for association.',
            ]);
        }

        if ($received->account_activity_id) {
            $received->forceFill([
                'account_id' => $account->id,
                'account_contact_info_id' => $contact?->id ?? $received->account_contact_info_id,
                'association_status' => $status,
            ])->save();

            return $received->fresh(['account', 'contactInfo', 'activity']);
        }

        $typeId = ActivityType::query()->where('code', 'sms_receive')->value('id');
        if (! $typeId) {
            throw ValidationException::withMessages([
                'account_id' => 'SMS Receive activity type is not configured.',
            ]);
        }

        /** @var AccountActivity $activity */
        $activity = $account->activities()->create([
            'occurred_at' => $received->received_at ?? now(),
            'activity_type_id' => $typeId,
            'actor_user_id' => null,
            'agent_profile_id' => null,
            'assigned_agent_profile_id' => $account->assigned_agent_profile_id,
            'entity_status_id' => $account->entity_status_id,
            'entity_action_code_id' => $account->entity_action_code_id,
            'reference_text' => $received->message,
            'reference_contact_info_id' => $contact?->id,
            'remarks' => 'Inbound SMS'.($received->device_id ? " via {$received->device_id}" : ''),
        ]);

        $received->forceFill([
            'account_id' => $account->id,
            'account_contact_info_id' => $contact?->id,
            'account_activity_id' => $activity->id,
            'association_status' => $status,
        ])->save();

        $account->forceFill([
            'last_activity_at' => $activity->occurred_at,
            'last_activity_type_id' => $typeId,
            'last_reference_text' => $received->message,
            'last_reference_contact_info_id' => $contact?->id,
        ])->save();

        $this->activityTotals->sync($account->fresh());

        return $received->fresh(['account', 'contactInfo', 'activity']);
    }

    protected function findMatchingContact(string $sender, ?int $accountId = null): ?AccountContactInfo
    {
        $normalized = preg_replace('/[\s\-+]/', '', $sender) ?: '';
        if ($normalized === '') {
            return null;
        }

        $query = AccountContactInfo::query()
            ->withoutGlobalScopes()
            ->whereIn('type', ['mobile', 'landline'])
            ->where(function ($q) use ($sender, $normalized) {
                $q->where('value', $sender)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(value, ' ', ''), '-', ''), '+', '') = ?", [$normalized]);
            });

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->orderBy('id')->first();
    }
}
