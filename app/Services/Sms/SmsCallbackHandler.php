<?php

namespace App\Services\Sms;

use App\Enums\SmsQueueItemStatus;
use App\Models\SmsCallbackEvent;
use App\Models\SmsDevice;
use App\Models\SmsQueueItem;
use App\Models\SmsSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsCallbackHandler
{
    public function __construct(
        protected SmsServiceClient $client,
        protected SmsDeviceSyncService $deviceSync,
        protected SmsReceivedService $receivedService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, duplicate?: bool}
     */
    public function handle(array $payload): array
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        if ($eventId === '') {
            return ['success' => true];
        }

        if (SmsCallbackEvent::query()->where('event_id', $eventId)->exists()) {
            return ['success' => true, 'duplicate' => true];
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        $responseType = (string) ($payload['response_type'] ?? 'Notification');
        $deviceId = isset($payload['device_id']) ? (string) $payload['device_id'] : null;
        $body = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];

        DB::transaction(function () use ($eventId, $eventType, $responseType, $deviceId, $body, $payload) {
            $event = SmsCallbackEvent::query()->create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'response_type' => $responseType,
                'device_id' => $deviceId,
                'payload' => $body,
                'event_timestamp' => $payload['timestamp'] ?? null,
                'processed_at' => now(),
            ]);

            match ($eventType) {
                'SmsSent' => $this->handleSmsSent($eventId, $deviceId, $body),
                'SmsFailed' => $this->handleSmsFailed($eventId, $deviceId, $body),
                'SmsReceived' => $this->receivedService->persistFromCallback($event, $deviceId, $body),
                'DeviceConnected', 'DeviceDisconnected', 'DeviceRecovered', 'DeviceHealthChanged' => $this->handleDeviceLifecycle($deviceId, $eventType, $body),
                'DeviceError' => $this->handleDeviceError($deviceId, $body),
                'SmsDeleted' => null,
                default => null,
            };
        });

        if (in_array($responseType, ['RestartDevice', 'DeleteDevice'], true) && $deviceId) {
            $settings = SmsSetting::current();
            if (! $settings->auto_device_recovery) {
                Log::info('SMS auto device recovery disabled; skipping follow-up', [
                    'response_type' => $responseType,
                    'device_id' => $deviceId,
                ]);
            } else {
                try {
                    if ($responseType === 'RestartDevice') {
                        $this->client->restartDevice($deviceId);
                    } else {
                        $this->client->deleteDevice($deviceId);
                    }
                } catch (Throwable $e) {
                    Log::warning('SMS callback follow-up action failed', [
                        'response_type' => $responseType,
                        'device_id' => $deviceId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['success' => true];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function handleSmsSent(string $eventId, ?string $deviceId, array $body): void
    {
        $reference = (string) ($body['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $item = SmsQueueItem::query()->where('reference', $reference)->first();
        if (! $item || $item->status === SmsQueueItemStatus::Sent) {
            return;
        }

        $item->forceFill([
            'status' => SmsQueueItemStatus::Sent,
            'runtime_device_id' => $deviceId ?? $item->runtime_device_id,
            'sent_at' => $body['sent_at'] ?? now(),
            'last_event_id' => $eventId,
            'error_code' => null,
            'error_message' => null,
        ])->save();

        $item->batch?->refreshCounts();

        $settings = SmsSetting::current();
        $settings->forceFill([
            'sms_sent_since_start' => ((int) $settings->sms_sent_since_start) + 1,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function handleSmsFailed(string $eventId, ?string $deviceId, array $body): void
    {
        $reference = (string) ($body['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $item = SmsQueueItem::query()->where('reference', $reference)->first();
        if (! $item || in_array($item->status, [SmsQueueItemStatus::Sent, SmsQueueItemStatus::Failed], true)) {
            return;
        }

        $item->forceFill([
            'status' => SmsQueueItemStatus::Failed,
            'runtime_device_id' => $deviceId ?? $item->runtime_device_id,
            'failed_at' => now(),
            'last_event_id' => $eventId,
            'error_code' => isset($body['error_code']) ? (string) $body['error_code'] : null,
            'error_message' => isset($body['error_message']) ? (string) $body['error_message'] : 'SMS send failed',
        ])->save();

        $item->batch?->refreshCounts();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function handleDeviceLifecycle(?string $deviceId, string $eventType, array $body): void
    {
        if (! $deviceId) {
            return;
        }

        $updates = [
            'last_health_at' => now(),
        ];

        if ($eventType === 'DeviceConnected') {
            $updates['state'] = (string) ($body['state'] ?? 'connected');
            $updates['last_error'] = null;
        } elseif ($eventType === 'DeviceDisconnected') {
            $updates['state'] = 'disconnected';
            $updates['last_error'] = isset($body['reason']) ? (string) $body['reason'] : 'Disconnected';
        } elseif ($eventType === 'DeviceRecovered') {
            $updates['state'] = (string) ($body['current_state'] ?? 'connected');
            $updates['health'] = (string) ($body['current_health'] ?? $body['current_state'] ?? 'Healthy');
            $updates['last_error'] = null;
        } elseif ($eventType === 'DeviceHealthChanged') {
            $updates['health'] = (string) ($body['current_health'] ?? '');
        }

        SmsDevice::query()
            ->where('runtime_device_id', $deviceId)
            ->update($updates);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function handleDeviceError(?string $deviceId, array $body): void
    {
        if (! $deviceId) {
            return;
        }

        $this->deviceSync->recordError(
            $deviceId,
            isset($body['error_code']) ? (string) $body['error_code'] : null,
            isset($body['error_message']) ? (string) $body['error_message'] : 'Device error',
            isset($body['recommended_action']) ? (string) $body['recommended_action'] : null,
            $body,
        );
    }
}
