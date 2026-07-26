<?php

namespace App\Services\Sms;

use App\Enums\SmsBatchStatus;
use App\Enums\SmsQueueItemStatus;
use App\Enums\SmsTargetMode;
use App\Models\SmsDevice;
use App\Models\SmsDeviceGroup;
use App\Models\SmsQueueItem;
use App\Models\SmsSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsDispatchService
{
    public function __construct(
        protected SmsServiceClient $client,
        protected SmsServiceProcessManager $processManager,
        protected SmsDeviceSyncService $deviceSync,
    ) {}

    /**
     * @return array{dispatched: int, available_devices: int, pending: int, alert: string|null, message: string}
     */
    public function tick(): array
    {
        $settings = SmsSetting::current();
        $this->client->refreshSettings($settings);

        // Never auto-start — service must be started manually from the SMS Dashboard.
        $status = $this->processManager->status($settings);
        if (! $status['running']) {
            $pending = SmsQueueItem::query()->where('status', SmsQueueItemStatus::Queued)->count();
            $settings->setAlert('service_down', $status['error'] ?? 'SMS service is not running.');

            return [
                'dispatched' => 0,
                'available_devices' => 0,
                'pending' => $pending,
                'alert' => 'service_down',
                'message' => $status['error'] ?? 'SMS service is not running.',
            ];
        }

        if (! $settings->service_started_at) {
            $settings->forceFill([
                'service_started_at' => now(),
                'sms_sent_since_start' => $settings->sms_sent_since_start ?: 0,
            ])->save();
        }

        $this->deviceSync->syncFromService($this->client);

        $pending = $this->dispatchableQueuedCount();
        $available = $this->availableRuntimeDeviceIds();

        if ($pending > 0 && count($available) === 0) {
            $settings->setAlert(
                'no_available_device',
                'SMS service is running. 0 devices available. Queued messages are waiting.',
            );

            return [
                'dispatched' => 0,
                'available_devices' => 0,
                'pending' => $pending,
                'alert' => 'no_available_device',
                'message' => 'SMS service is running. 0 devices available.',
            ];
        }

        if ($pending === 0) {
            if (in_array($settings->last_alert, ['no_available_device', 'service_down'], true)) {
                $settings->clearAlert();
            }

            return [
                'dispatched' => 0,
                'available_devices' => count($available),
                'pending' => 0,
                'alert' => null,
                'message' => 'Queue is idle.',
            ];
        }

        $dispatched = 0;
        $usedRuntimeIds = [];
        $maxClaims = max(1, count($available));

        for ($i = 0; $i < $maxClaims; $i++) {
            $remainingAvailable = array_values(array_diff($available, $usedRuntimeIds));
            if ($remainingAvailable === []) {
                break;
            }

            $claimed = $this->claimNextTargetedItem($remainingAvailable);
            if (! $claimed) {
                break;
            }

            [$item, $runtimeDeviceId] = $claimed;
            $usedRuntimeIds[] = $runtimeDeviceId;

            try {
                $this->client->sendSms(
                    $runtimeDeviceId,
                    (string) $item->recipient,
                    $item->message,
                    $item->reference,
                );
                $dispatched++;
                $item->batch?->refreshCounts();
            } catch (Throwable $e) {
                Log::warning('SMS send accept failed', [
                    'reference' => $item->reference,
                    'device_id' => $runtimeDeviceId,
                    'error' => $e->getMessage(),
                ]);

                $item->forceFill([
                    'status' => SmsQueueItemStatus::Failed,
                    'error_code' => 'send_accept_failed',
                    'error_message' => $e->getMessage(),
                    'failed_at' => now(),
                    'runtime_device_id' => null,
                    'assigned_sms_device_id' => null,
                ])->save();
                $item->batch?->refreshCounts();
            }
        }

        if ($dispatched > 0) {
            $settings->clearAlert('no_available_device');
            $settings->clearAlert('service_down');
        }

        return [
            'dispatched' => $dispatched,
            'available_devices' => count($available),
            'pending' => $this->dispatchableQueuedCount(),
            'alert' => null,
            'message' => "Dispatched {$dispatched} SMS.",
        ];
    }

    protected function dispatchableQueuedCount(): int
    {
        return SmsQueueItem::query()
            ->where('status', SmsQueueItemStatus::Queued)
            ->whereHas('batch', function ($q) {
                $q->whereNotIn('status', [
                    SmsBatchStatus::Paused->value,
                    SmsBatchStatus::Cancelled->value,
                ]);
            })
            ->count();
    }

    /**
     * @return list<string>
     */
    protected function availableRuntimeDeviceIds(): array
    {
        $busy = SmsQueueItem::query()
            ->where('status', SmsQueueItemStatus::Sending)
            ->whereNotNull('runtime_device_id')
            ->pluck('runtime_device_id')
            ->all();

        $devices = SmsDevice::query()
            ->where('enabled', true)
            ->whereNotNull('runtime_device_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $available = [];
        foreach ($devices as $device) {
            $runtimeId = (string) $device->runtime_device_id;
            if (in_array($runtimeId, $busy, true)) {
                continue;
            }

            $state = strtolower((string) $device->state);
            $health = strtolower((string) $device->health);

            $connected = in_array($state, ['connected', 'ready', 'online', ''], true) || $state === '';
            $healthy = in_array($health, ['healthy', 'ok', 'good', ''], true) || $health === '';

            // Prefer explicit healthy/connected; accept unknown health if connected and no last_error.
            if (($connected && $healthy) || ($connected && ! $device->last_error)) {
                $available[] = $runtimeId;
            }
        }

        // Also accept runtime devices discovered but not yet linked to registry rows.
        try {
            foreach ($this->client->listDevices() as $remote) {
                $id = (string) ($remote['device_id'] ?? $remote['id'] ?? '');
                if ($id === '' || in_array($id, $available, true) || in_array($id, $busy, true)) {
                    continue;
                }
                $state = strtolower((string) ($remote['state'] ?? $remote['status'] ?? 'connected'));
                $health = strtolower((string) ($remote['health'] ?? $remote['current_health'] ?? 'healthy'));
                if (
                    ! in_array($state, ['disconnected', 'offline', 'error'], true)
                    && ! in_array($health, ['unhealthy', 'error', 'critical'], true)
                ) {
                    $available[] = $id;
                }
            }
        } catch (Throwable) {
            // Mirror-only availability already computed.
        }

        return array_values(array_unique($available));
    }

    /**
     * @param  list<string>  $availableRuntimeIds
     * @return array{0: SmsQueueItem, 1: string}|null
     */
    protected function claimNextTargetedItem(array $availableRuntimeIds): ?array
    {
        return DB::transaction(function () use ($availableRuntimeIds) {
            $candidates = SmsQueueItem::query()
                ->where('sms_queue_items.status', SmsQueueItemStatus::Queued)
                ->whereNotNull('sms_queue_items.recipient')
                ->whereHas('batch', function ($q) {
                    $q->whereNotIn('status', [
                        SmsBatchStatus::Paused->value,
                        SmsBatchStatus::Cancelled->value,
                    ]);
                })
                ->join('sms_batches', 'sms_batches.id', '=', 'sms_queue_items.sms_batch_id')
                ->orderBy('sms_batches.priority')
                ->orderBy('sms_queue_items.id')
                ->select('sms_queue_items.*')
                ->lockForUpdate()
                ->limit(50)
                ->get();

            foreach ($candidates as $row) {
                $item = SmsQueueItem::query()->whereKey($row->id)->lockForUpdate()->first();
                if (! $item || $item->status !== SmsQueueItemStatus::Queued) {
                    continue;
                }

                $device = $this->resolveDeviceForItem($item, $availableRuntimeIds);
                if (! $device) {
                    continue;
                }

                $runtimeId = (string) $device->runtime_device_id;
                $item->forceFill([
                    'status' => SmsQueueItemStatus::Sending,
                    'runtime_device_id' => $runtimeId,
                    'assigned_sms_device_id' => $device->id,
                    'error_code' => null,
                    'error_message' => null,
                ])->save();

                if ($item->target_mode === SmsTargetMode::GroupRoundRobin && $item->target_sms_device_group_id) {
                    $group = SmsDeviceGroup::query()->whereKey($item->target_sms_device_group_id)->lockForUpdate()->first();
                    $group?->advanceRoundRobinCursor($device);
                }

                return [$item->fresh(['batch']), $runtimeId];
            }

            return null;
        });
    }

    /**
     * @param  list<string>  $availableRuntimeIds
     */
    protected function resolveDeviceForItem(SmsQueueItem $item, array $availableRuntimeIds): ?SmsDevice
    {
        $mode = $item->target_mode instanceof SmsTargetMode
            ? $item->target_mode
            : ($item->target_mode ? SmsTargetMode::tryFrom((string) $item->target_mode) : null);

        // Reply / specific pin: runtime already set.
        if ($item->runtime_device_id) {
            $runtimeId = (string) $item->runtime_device_id;
            if (! in_array($runtimeId, $availableRuntimeIds, true)) {
                return null;
            }

            return SmsDevice::query()
                ->where('runtime_device_id', $runtimeId)
                ->where('enabled', true)
                ->first();
        }

        if ($mode === SmsTargetMode::SpecificDevice && $item->target_sms_device_id) {
            $device = SmsDevice::query()
                ->whereKey($item->target_sms_device_id)
                ->where('enabled', true)
                ->whereNotNull('runtime_device_id')
                ->first();

            if (! $device || ! in_array((string) $device->runtime_device_id, $availableRuntimeIds, true)) {
                return null;
            }

            return $device;
        }

        if ($mode === SmsTargetMode::GroupRoundRobin && $item->target_sms_device_group_id) {
            $group = SmsDeviceGroup::query()->whereKey($item->target_sms_device_group_id)->first();
            if (! $group || ! $group->enabled) {
                return null;
            }

            return $group->nextRoundRobinDevice($availableRuntimeIds);
        }

        // Legacy untargeted: first available registry device.
        return SmsDevice::query()
            ->where('enabled', true)
            ->whereIn('runtime_device_id', $availableRuntimeIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
