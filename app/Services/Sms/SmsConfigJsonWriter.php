<?php

namespace App\Services\Sms;

use App\Enums\SmsDeviceType;
use App\Models\SmsDevice;
use App\Models\SmsSetting;
use RuntimeException;

class SmsConfigJsonWriter
{
    public const GENERATED_BY = 'Collectimate Laravel';

    /**
     * @var list<string>
     */
    protected const DEVICE_SCHEMA_KEYS = [
        'id',
        'type',
        'enabled',
        'friendly_name',
        'port',
        'baud_rate',
        'encoding',
        'auto_delete',
        'poll_interval_ms',
        'health_check_interval_seconds',
        'command_timeout_ms',
        'prompt_timeout_ms',
        'operation_timeout_seconds',
        'sync_timeout_seconds',
        'send_retry_count',
        'failure_retry_count',
        'restart_retry_count',
        'receive_enabled',
        'send_enabled',
        'recipient_format',
    ];

    /**
     * Always write the full root document from admin-owned SMS settings + devices.
     *
     * @return array{path: string, written: bool, devices: int, mode: string}
     */
    public function write(?SmsSetting $settings = null): array
    {
        $settings ??= SmsSetting::current();
        $settings->ensureConfigSections();
        $path = $settings->resolvedConfigJsonPath();

        if (! $path) {
            throw new RuntimeException('SMS config.json path is not set. Set Config.json path in SMS Configuration.');
        }

        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create config directory: {$dir}");
        }

        $devices = SmsDevice::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SmsDevice $device) => $this->mapDevice($device))
            ->values()
            ->all();

        $existingVersion = 0;
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $existingVersion = (int) ($decoded['version'] ?? 0);
            }
        }

        $service = $settings->resolvedConfigService();
        $service['auto_detect_at_devices'] = (bool) $settings->auto_detect_at_ports;
        $ports = $settings->http_ports_to_test ?? [8080];
        if (is_array($ports) && isset($ports[0])) {
            $service['listen_port'] = (int) $ports[0];
        }

        $callbacks = $settings->resolvedConfigCallbacks();
        $callbacks['base_url'] = $this->normalizeCallbackUrl(
            (string) ($callbacks['base_url'] ?? $settings->callback_base_url ?? ''),
        );
        $callbacks['api_key'] = (string) ($callbacks['api_key'] ?? $settings->api_key ?? '');

        if ($callbacks['base_url'] === '' || $callbacks['api_key'] === '') {
            throw new RuntimeException('config.json requires callbacks.base_url and callbacks.api_key.');
        }
        if (trim((string) ($service['service_id'] ?? '')) === '') {
            throw new RuntimeException('config.json requires service.service_id.');
        }

        $config = [
            'version' => max(1, $existingVersion + 1),
            'generated_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'generated_by' => self::GENERATED_BY,
            'service' => $service,
            'logging' => $settings->resolvedConfigLogging(),
            'http' => $settings->resolvedConfigHttp(),
            'callbacks' => $callbacks,
            'queue' => $settings->resolvedConfigQueue(),
            'devices' => $devices,
        ];

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode SMS config.json.');
        }

        if (file_put_contents($path, $json."\n") === false) {
            throw new RuntimeException("Failed to write SMS config.json at: {$path}");
        }

        return [
            'path' => $path,
            'written' => true,
            'devices' => count($devices),
            'mode' => 'full',
        ];
    }

    protected function normalizeCallbackUrl(string $callbackUrl): string
    {
        $callbackUrl = rtrim($callbackUrl, '/');
        if ($callbackUrl === '') {
            return '';
        }
        if (! str_ends_with($callbackUrl, '/api/sms/callback')) {
            $callbackUrl .= '/api/sms/callback';
        }

        return $callbackUrl;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapDevice(SmsDevice $device): array
    {
        $type = $device->type instanceof SmsDeviceType
            ? $device->type->value
            : (string) $device->type;

        $raw = is_array($device->config) ? $device->config : [];
        $id = $device->runtime_device_id ?: ('LARAVEL-'.$device->id);

        $port = trim((string) ($raw['port'] ?? $raw['com_port'] ?? ''));
        if ($port === '' && $type === SmsDeviceType::Demo->value) {
            $port = 'COM99';
        }
        if ($port === '') {
            throw new RuntimeException("Device port is required for device: {$id}");
        }

        $baud = $raw['baud_rate'] ?? $raw['baud'] ?? 115200;
        $baudRate = (int) $baud;
        if ($baudRate <= 0) {
            $baudRate = 115200;
        }

        $defaults = [
            'id' => $id,
            'type' => $type,
            'enabled' => (bool) $device->enabled,
            'friendly_name' => (string) ($device->name ?? ''),
            'port' => $port,
            'baud_rate' => $baudRate,
            'encoding' => 'GSM7',
            'auto_delete' => true,
            'poll_interval_ms' => 500,
            'health_check_interval_seconds' => 30,
            'command_timeout_ms' => 3000,
            'prompt_timeout_ms' => 5000,
            'operation_timeout_seconds' => 30,
            'sync_timeout_seconds' => 45,
            'send_retry_count' => 3,
            'failure_retry_count' => 3,
            'restart_retry_count' => 2,
            'receive_enabled' => true,
            'send_enabled' => true,
            'recipient_format' => 'International',
        ];

        foreach (self::DEVICE_SCHEMA_KEYS as $key) {
            if (in_array($key, ['id', 'type', 'enabled', 'friendly_name', 'port', 'baud_rate'], true)) {
                continue;
            }
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $defaults[$key] = $value;
        }

        $op = (int) $defaults['operation_timeout_seconds'];
        $sync = (int) $defaults['sync_timeout_seconds'];
        if ($sync <= $op) {
            $defaults['sync_timeout_seconds'] = $op + 15;
        }

        if ($type === SmsDeviceType::Demo->value) {
            $rate = $raw['demo_send_success_rate'] ?? SmsDevice::DEFAULT_DEMO_SEND_SUCCESS_RATE;
            $rate = is_numeric($rate) ? (float) $rate : SmsDevice::DEFAULT_DEMO_SEND_SUCCESS_RATE;
            $defaults['demo_send_success_rate'] = max(0.0, min(1.0, $rate));

            $interval = $raw['demo_receive_interval_seconds'] ?? SmsDevice::DEFAULT_DEMO_RECEIVE_INTERVAL_SECONDS;
            $interval = is_numeric($interval) ? (int) $interval : SmsDevice::DEFAULT_DEMO_RECEIVE_INTERVAL_SECONDS;
            $defaults['demo_receive_interval_seconds'] = max(1, $interval);
        }

        return $defaults;
    }
}
