<?php

namespace App\Services\Sms;

use App\Enums\ContactInfoType;
use App\Enums\SmsBatchSource;
use App\Enums\SmsBatchStatus;
use App\Enums\SmsQueueItemStatus;
use App\Enums\SmsTargetMode;
use App\Models\Account;
use App\Models\AccountActivity;
use App\Models\AccountActivityFile;
use App\Models\AccountContactInfo;
use App\Models\SmsBatch;
use App\Models\SmsDevice;
use App\Models\SmsQueueItem;
use App\Models\User;
use App\Services\AccountActivityTotalsSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SmsQueueService
{
    public function __construct(
        protected AccountActivityTotalsSync $activityTotals,
    ) {}

    /**
     * @param  Collection<int, array{account: Account, activity: AccountActivity, message: string, recipient: ?string}>  $entries
     * @param  array{mode: SmsTargetMode, sms_device_group_id?: int|null, sms_device_id?: int|null}|null  $targeting
     */
    public function enqueueBatch(
        SmsBatchSource $source,
        User $user,
        Collection $entries,
        ?string $messageBodySnapshot = null,
        ?array $metadata = null,
        ?AccountActivity $primaryActivity = null,
        ?array $targeting = null,
    ): SmsBatch {
        return DB::transaction(function () use ($source, $user, $entries, $messageBodySnapshot, $metadata, $primaryActivity, $targeting) {
            $user->loadMissing('agentProfile');

            $targetMode = $targeting['mode'] ?? null;
            $targetGroupId = $targeting['sms_device_group_id'] ?? null;
            $targetDeviceId = $targeting['sms_device_id'] ?? null;
            $pinRuntimeId = null;
            $pinAssignedId = null;

            if ($targetMode === SmsTargetMode::SpecificDevice && $targetDeviceId) {
                $device = SmsDevice::query()->whereKey($targetDeviceId)->first();
                $pinRuntimeId = $device?->runtime_device_id;
                $pinAssignedId = $device?->id;
            }

            $meta = $metadata ?? [];
            if ($targetMode instanceof SmsTargetMode) {
                $meta['sms_target_mode'] = $targetMode->value;
                $meta['sms_device_group_id'] = $targetGroupId;
                $meta['sms_device_id'] = $targetDeviceId;
            }

            $batch = SmsBatch::query()->create([
                'source' => $source,
                'created_by_user_id' => $user->id,
                'agent_profile_id' => $user->agentProfile?->id,
                'account_activity_id' => $primaryActivity?->id,
                'message_body' => $messageBodySnapshot,
                'status' => SmsBatchStatus::Pending,
                'priority' => 100,
                'total' => $entries->count(),
                'queued' => 0,
                'sending' => 0,
                'sent' => 0,
                'failed' => 0,
                'cancelled' => 0,
                'metadata' => $meta,
            ]);

            foreach ($entries as $entry) {
                /** @var Account $account */
                $account = $entry['account'];
                /** @var AccountActivity $activity */
                $activity = $entry['activity'];
                $message = (string) $entry['message'];
                $recipient = $entry['recipient'] ?? null;

                $status = SmsQueueItemStatus::Queued;
                $errorMessage = null;

                if (! $recipient) {
                    $status = SmsQueueItemStatus::Failed;
                    $errorMessage = 'No phone/mobile contact available for this account.';
                }

                SmsQueueItem::query()->create([
                    'sms_batch_id' => $batch->id,
                    'account_id' => $account->id,
                    'account_activity_id' => $activity->id,
                    'recipient' => $recipient,
                    'message' => $message,
                    'status' => $status,
                    'error_message' => $errorMessage,
                    'failed_at' => $status === SmsQueueItemStatus::Failed ? now() : null,
                    'target_mode' => $targetMode,
                    'target_sms_device_group_id' => $targetMode === SmsTargetMode::GroupRoundRobin ? $targetGroupId : null,
                    'target_sms_device_id' => $targetMode === SmsTargetMode::SpecificDevice ? $targetDeviceId : null,
                    'runtime_device_id' => $status === SmsQueueItemStatus::Queued ? $pinRuntimeId : null,
                    'assigned_sms_device_id' => $status === SmsQueueItemStatus::Queued ? $pinAssignedId : null,
                ]);
            }

            $batch->refreshCounts();

            return $batch->fresh();
        });
    }

    public function resolveRecipientFromContact(?AccountContactInfo $contact): ?string
    {
        if (! $contact) {
            return null;
        }

        $type = $contact->type instanceof ContactInfoType
            ? $contact->type
            : ContactInfoType::tryFrom((string) $contact->type);

        if (! in_array($type, [ContactInfoType::Mobile, ContactInfoType::Landline], true)) {
            return null;
        }

        $value = preg_replace('/\s+/', '', (string) $contact->value);

        return $value !== '' ? $value : null;
    }

    public function resolvePrimaryPhone(Account $account): ?string
    {
        $contacts = $account->relationLoaded('contactInfos')
            ? $account->contactInfos
            : $account->contactInfos()->get();

        $phoneContacts = $contacts->filter(function ($contact) {
            $type = $contact->type instanceof ContactInfoType
                ? $contact->type
                : ContactInfoType::tryFrom((string) $contact->type);

            return in_array($type, [ContactInfoType::Mobile, ContactInfoType::Landline], true);
        })->values();

        $primary = $phoneContacts->firstWhere('is_primary', true) ?? $phoneContacts->first(
            fn ($c) => ($c->type instanceof ContactInfoType ? $c->type : ContactInfoType::tryFrom((string) $c->type)) === ContactInfoType::Mobile
        ) ?? $phoneContacts->first();

        return $this->resolveRecipientFromContact($primary);
    }

    /**
     * Bulk SMS: mobile contacts only (landlines excluded).
     *
     * @param  'primary_mobile'|'all_mobiles'  $scope
     * @return Collection<int, string>
     */
    public function resolveBulkMobileRecipients(Account $account, string $scope): Collection
    {
        $contacts = $account->relationLoaded('contactInfos')
            ? $account->contactInfos
            : $account->contactInfos()->get();

        $mobiles = $contacts->filter(function ($contact) {
            $type = $contact->type instanceof ContactInfoType
                ? $contact->type
                : ContactInfoType::tryFrom((string) $contact->type);

            return $type === ContactInfoType::Mobile;
        })->values();

        if ($mobiles->isEmpty()) {
            return collect();
        }

        if ($scope === 'primary_mobile') {
            $primary = $mobiles->firstWhere('is_primary', true) ?? $mobiles->first();
            $recipient = $this->resolveRecipientFromContact($primary);

            return $recipient ? collect([$recipient]) : collect();
        }

        return $mobiles
            ->map(fn ($contact) => $this->resolveRecipientFromContact($contact))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return array{batch: SmsBatch, activities_deleted: int}
     */
    public function cancelBatch(SmsBatch $batch): array
    {
        return DB::transaction(function () use ($batch) {
            $items = SmsQueueItem::query()
                ->where('sms_batch_id', $batch->id)
                ->where('status', SmsQueueItemStatus::Queued)
                ->get();

            $activityIds = $items->pluck('account_activity_id')->filter()->unique()->values();
            $accountIds = $items->pluck('account_id')->unique()->values();

            SmsQueueItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->update([
                    'status' => SmsQueueItemStatus::Cancelled,
                    'account_activity_id' => null,
                    'updated_at' => now(),
                ]);

            $activitiesDeleted = 0;
            if ($activityIds->isNotEmpty()) {
                $files = AccountActivityFile::query()
                    ->whereIn('account_activity_id', $activityIds)
                    ->get();
                foreach ($files as $file) {
                    Storage::disk($file->disk ?: 'local')->delete($file->path);
                    $file->delete();
                }

                $activitiesDeleted = AccountActivity::query()
                    ->whereIn('id', $activityIds)
                    ->delete();
            }

            // Also clear batch-level primary activity if it was among deleted.
            if ($batch->account_activity_id && $activityIds->contains($batch->account_activity_id)) {
                $batch->account_activity_id = null;
            }

            $batch->forceFill([
                'status' => SmsBatchStatus::Cancelled,
            ])->save();

            $batch->refreshCounts();

            foreach ($accountIds as $accountId) {
                $account = Account::query()->withoutGlobalScopes()->find($accountId);
                if ($account) {
                    $this->activityTotals->sync($account);
                }
            }

            return [
                'batch' => $batch->fresh(),
                'activities_deleted' => (int) $activitiesDeleted,
            ];
        });
    }

    public function pauseBatch(SmsBatch $batch): SmsBatch
    {
        if (in_array($batch->status, [SmsBatchStatus::Cancelled, SmsBatchStatus::Completed, SmsBatchStatus::Failed], true)) {
            return $batch;
        }

        $batch->forceFill(['status' => SmsBatchStatus::Paused])->save();

        return $batch->fresh();
    }

    public function resumeBatch(SmsBatch $batch): SmsBatch
    {
        if ($batch->status !== SmsBatchStatus::Paused) {
            return $batch;
        }

        $batch->forceFill(['status' => SmsBatchStatus::Pending])->save();
        $batch->refreshCounts();

        return $batch->fresh();
    }

    public function bumpPriority(SmsBatch $batch, string $direction): SmsBatch
    {
        $priority = (int) $batch->priority;
        $priority = $direction === 'up'
            ? max(1, $priority - 10)
            : min(1000, $priority + 10);

        $batch->forceFill(['priority' => $priority])->save();

        return $batch->fresh();
    }

    public function setPriority(SmsBatch $batch, int $priority): SmsBatch
    {
        $batch->forceFill([
            'priority' => max(1, min(1000, $priority)),
        ])->save();

        return $batch->fresh();
    }

    /**
     * @param  array{recipient?: string, message?: string}  $data
     */
    public function updateQueuedItem(SmsQueueItem $item, array $data): SmsQueueItem
    {
        if ($item->status !== SmsQueueItemStatus::Queued) {
            throw ValidationException::withMessages([
                'item' => 'Only queued SMS items can be edited.',
            ]);
        }

        $updates = [];
        if (array_key_exists('recipient', $data)) {
            $recipient = preg_replace('/\s+/', '', (string) $data['recipient']);
            if ($recipient === '') {
                throw ValidationException::withMessages([
                    'recipient' => 'Recipient is required.',
                ]);
            }
            $updates['recipient'] = $recipient;
        }
        if (array_key_exists('message', $data)) {
            $message = (string) $data['message'];
            if (trim($message) === '') {
                throw ValidationException::withMessages([
                    'message' => 'Message is required.',
                ]);
            }
            $updates['message'] = $message;
        }

        if ($updates !== []) {
            $item->forceFill($updates)->save();
        }

        return $item->fresh();
    }

    /**
     * @return array{item: SmsQueueItem, batch: SmsBatch, activities_deleted: int}
     */
    public function cancelItem(SmsQueueItem $item): array
    {
        return DB::transaction(function () use ($item) {
            $item = SmsQueueItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($item->status !== SmsQueueItemStatus::Queued) {
                throw ValidationException::withMessages([
                    'item' => 'Only queued SMS items can be cancelled.',
                ]);
            }

            $activityId = $item->account_activity_id;
            $accountId = $item->account_id;

            $activitiesDeleted = $this->deleteLinkedActivities(
                collect([$activityId])->filter()->values(),
                collect([$accountId])->filter()->values(),
            );

            $item->forceFill([
                'status' => SmsQueueItemStatus::Cancelled,
                'account_activity_id' => null,
            ])->save();

            $batch = SmsBatch::query()->whereKey($item->sms_batch_id)->lockForUpdate()->firstOrFail();
            if ($batch->account_activity_id && $activityId && (int) $batch->account_activity_id === (int) $activityId) {
                $batch->account_activity_id = null;
                $batch->save();
            }

            $batch->refreshCounts();

            return [
                'item' => $item->fresh(),
                'batch' => $batch->fresh(),
                'activities_deleted' => $activitiesDeleted,
            ];
        });
    }

    /**
     * Hard-delete a batch. Rejects while any item is sending. Cancels remaining queued first.
     *
     * @return array{activities_deleted: int}
     */
    public function deleteBatch(SmsBatch $batch): array
    {
        return DB::transaction(function () use ($batch) {
            $batch = SmsBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            $sending = SmsQueueItem::query()
                ->where('sms_batch_id', $batch->id)
                ->where('status', SmsQueueItemStatus::Sending)
                ->exists();

            if ($sending) {
                throw ValidationException::withMessages([
                    'batch' => 'Cannot delete batch while an SMS is sending. Wait or cancel after it finishes.',
                ]);
            }

            $batchId = $batch->id;
            $cancelResult = $this->cancelBatch($batch->fresh());
            $activitiesDeleted = (int) $cancelResult['activities_deleted'];

            SmsQueueItem::query()->where('sms_batch_id', $batchId)->delete();
            SmsBatch::query()->whereKey($batchId)->delete();

            return [
                'activities_deleted' => $activitiesDeleted,
            ];
        });
    }

    /**
     * @param  Collection<int, mixed>  $activityIds
     * @param  Collection<int, mixed>  $accountIds
     */
    protected function deleteLinkedActivities(Collection $activityIds, Collection $accountIds): int
    {
        $activitiesDeleted = 0;
        if ($activityIds->isNotEmpty()) {
            $files = AccountActivityFile::query()
                ->whereIn('account_activity_id', $activityIds)
                ->get();
            foreach ($files as $file) {
                Storage::disk($file->disk ?: 'local')->delete($file->path);
                $file->delete();
            }

            $activitiesDeleted = (int) AccountActivity::query()
                ->whereIn('id', $activityIds)
                ->delete();
        }

        foreach ($accountIds as $accountId) {
            $account = Account::query()->withoutGlobalScopes()->find($accountId);
            if ($account) {
                $this->activityTotals->sync($account);
            }
        }

        return $activitiesDeleted;
    }
}
