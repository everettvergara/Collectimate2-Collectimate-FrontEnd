<?php

namespace App\Services\Sms;

use App\Enums\SmsDeviceType;
use App\Models\SmsDevice;
use App\Models\SmsDeviceError;
use App\Models\SmsDeviceGroup;
use Throwable;

class SmsDeviceSyncService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function syncFromService(SmsServiceClient $client): array
    {
        try {
            $devices = $client->listDevices();
        } catch (Throwable) {
            return [];
        }

        $seen = [];

        foreach ($devices as $remote) {
            $runtimeId = (string) ($remote['device_id'] ?? $remote['id'] ?? '');
            if ($runtimeId === '') {
                continue;
            }
            $seen[] = $runtimeId;

            $typeValue = (string) ($remote['type'] ?? 'AT');
            $type = SmsDeviceType::tryFrom($typeValue) ?? SmsDeviceType::At;

            $health = $remote['health'] ?? $remote['current_health'] ?? null;
            $state = $remote['state'] ?? $remote['status'] ?? null;
            $name = (string) ($remote['name'] ?? $runtimeId);

            $local = SmsDevice::query()->where('runtime_device_id', $runtimeId)->first();

            if (! $local && $type === SmsDeviceType::At) {
                // Prefer matching an enabled AT registry row without runtime id.
                $local = SmsDevice::query()
                    ->where('type', SmsDeviceType::At)
                    ->where('enabled', true)
                    ->whereNull('runtime_device_id')
                    ->orderBy('sort_order')
                    ->first();
            }

            if ($local) {
                $local->forceFill([
                    'runtime_device_id' => $runtimeId,
                    'health' => is_string($health) ? $health : (is_array($health) ? json_encode($health) : null),
                    'state' => is_string($state) ? $state : null,
                    'last_health_at' => now(),
                    'last_error' => $remote['last_error'] ?? $local->last_error,
                ])->save();
            } else {
                SmsDevice::query()->create([
                    'sms_device_group_id' => SmsDeviceGroup::ensureDefault()->id,
                    'type' => $type,
                    'name' => $name,
                    'enabled' => true,
                    'sort_order' => 100,
                    'config' => [],
                    'runtime_device_id' => $runtimeId,
                    'health' => is_string($health) ? $health : null,
                    'state' => is_string($state) ? $state : null,
                    'last_health_at' => now(),
                ]);
            }
        }

        return $devices;
    }

    public function recordError(
        string $runtimeDeviceId,
        ?string $errorCode,
        ?string $errorMessage,
        ?string $recommendedAction = null,
        ?array $payload = null,
    ): void {
        SmsDeviceError::query()->create([
            'runtime_device_id' => $runtimeDeviceId,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'recommended_action' => $recommendedAction,
            'payload' => $payload,
        ]);

        SmsDevice::query()
            ->where('runtime_device_id', $runtimeDeviceId)
            ->update([
                'last_error' => $errorMessage,
                'last_health_at' => now(),
            ]);
    }
}
