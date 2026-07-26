<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    protected $fillable = [
        'service_base_url',
        'api_key',
        'callback_base_url',
        'auto_start_enabled',
        'service_exe_path',
        'config_json_path',
        'auto_detect_at_ports',
        'auto_device_recovery',
        'dashboard_health_poll_seconds',
        'dashboard_list_poll_seconds',
        'http_ports_to_test',
        'config_service',
        'config_logging',
        'config_http',
        'config_callbacks',
        'config_queue',
        'service_started_at',
        'sms_sent_since_start',
        'last_alert',
        'last_alert_message',
        'last_alert_at',
    ];

    protected function casts(): array
    {
        return [
            'auto_start_enabled' => 'boolean',
            'auto_detect_at_ports' => 'boolean',
            'auto_device_recovery' => 'boolean',
            'dashboard_health_poll_seconds' => 'integer',
            'dashboard_list_poll_seconds' => 'integer',
            'http_ports_to_test' => 'array',
            'config_service' => 'array',
            'config_logging' => 'array',
            'config_http' => 'array',
            'config_callbacks' => 'array',
            'config_queue' => 'array',
            'service_started_at' => 'datetime',
            'sms_sent_since_start' => 'integer',
            'last_alert_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'service_base_url' => 'http://127.0.0.1:8080/api/v1',
            'api_key' => 'xxxxxxxx',
            'callback_base_url' => 'http://127.0.0.1:8000',
            'auto_start_enabled' => false,
            'service_exe_path' => 'C:\\collectimate\\collectimate_sms_service\\bin\\collectimate_sms_service.exe',
            'config_json_path' => 'C:\\collectimate\\collectimate_sms_service\\config\\config.json',
            'auto_detect_at_ports' => true,
            'auto_device_recovery' => true,
            'dashboard_health_poll_seconds' => 300,
            'dashboard_list_poll_seconds' => 8,
            'http_ports_to_test' => [8080],
            'sms_sent_since_start' => 0,
            'config_service' => static::defaultConfigService(),
            'config_logging' => static::defaultConfigLogging(),
            'config_http' => static::defaultConfigHttp(),
            'config_callbacks' => static::defaultConfigCallbacks(),
            'config_queue' => static::defaultConfigQueue(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfigService(): array
    {
        return [
            'service_id' => 'sms-service-01',
            'friendly_name' => 'Primary SMS Gateway',
            'listen_address' => '0.0.0.0',
            'listen_port' => 8080,
            'timezone' => 'Asia/Manila',
            'shutdown_timeout_seconds' => 15,
            'auto_detect_at_devices' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfigLogging(): array
    {
        return [
            'level' => 'info',
            'directory' => 'logs',
            'max_file_size_mb' => 20,
            'max_files' => 10,
            'console' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfigHttp(): array
    {
        return [
            'request_timeout_seconds' => 60,
            'worker_threads' => 4,
            'keep_alive' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfigCallbacks(
        string $callbackBaseUrl = 'http://127.0.0.1:8000',
        string $apiKey = 'xxxxxxxx',
    ): array {
        $base = rtrim($callbackBaseUrl, '/');
        if ($base !== '' && ! str_ends_with($base, '/api/sms/callback')) {
            $base .= '/api/sms/callback';
        }

        return [
            'base_url' => $base,
            'api_key' => $apiKey,
            'retry_attempts' => 5,
            'retry_delay_seconds' => 5,
            'connect_timeout_seconds' => 10,
            'request_timeout_seconds' => 20,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfigQueue(): array
    {
        return [
            'max_size' => 100000,
            'worker_threads' => 2,
        ];
    }

    public static function current(): self
    {
        $settings = static::query()->first() ?? static::query()->create(static::defaults());
        $settings->ensureConfigSections();

        SmsDevice::ensureDefaultDemo();

        return $settings->fresh() ?? $settings;
    }

    /**
     * Fill missing JSON section columns with instruction defaults.
     */
    public function ensureConfigSections(): void
    {
        $dirty = false;

        if (! is_array($this->config_service) || $this->config_service === []) {
            $this->config_service = static::defaultConfigService();
            $dirty = true;
        }
        if (! is_array($this->config_logging) || $this->config_logging === []) {
            $this->config_logging = static::defaultConfigLogging();
            $dirty = true;
        }
        if (! is_array($this->config_http) || $this->config_http === []) {
            $this->config_http = static::defaultConfigHttp();
            $dirty = true;
        }
        if (! is_array($this->config_callbacks) || $this->config_callbacks === []) {
            $this->config_callbacks = static::defaultConfigCallbacks(
                (string) ($this->callback_base_url ?: 'http://127.0.0.1:8000'),
                (string) ($this->api_key ?: 'xxxxxxxx'),
            );
            $dirty = true;
        }
        if (! is_array($this->config_queue) || $this->config_queue === []) {
            $this->config_queue = static::defaultConfigQueue();
            $dirty = true;
        }

        if ($dirty) {
            $this->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfigService(): array
    {
        return array_merge(static::defaultConfigService(), is_array($this->config_service) ? $this->config_service : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfigLogging(): array
    {
        return array_merge(static::defaultConfigLogging(), is_array($this->config_logging) ? $this->config_logging : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfigHttp(): array
    {
        return array_merge(static::defaultConfigHttp(), is_array($this->config_http) ? $this->config_http : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfigCallbacks(): array
    {
        return array_merge(
            static::defaultConfigCallbacks(
                (string) ($this->callback_base_url ?: 'http://127.0.0.1:8000'),
                (string) ($this->api_key ?: ''),
            ),
            is_array($this->config_callbacks) ? $this->config_callbacks : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfigQueue(): array
    {
        return array_merge(static::defaultConfigQueue(), is_array($this->config_queue) ? $this->config_queue : []);
    }

    public function resolvedConfigJsonPath(): ?string
    {
        $path = trim((string) $this->config_json_path);
        if ($path !== '') {
            return $path;
        }

        if (! $this->service_exe_path) {
            return null;
        }

        $dir = dirname($this->service_exe_path);

        return $dir.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.json';
    }

    /**
     * Laravel → C++ dashboard health poll interval (seconds), clamped to 30–3600.
     */
    public function resolvedDashboardHealthPollSeconds(): int
    {
        $seconds = (int) ($this->dashboard_health_poll_seconds ?: 300);

        return max(30, min(3600, $seconds));
    }

    /**
     * Dashboard pending-batches / received-SMS list poll interval (seconds), clamped to 5–300.
     */
    public function resolvedDashboardListPollSeconds(): int
    {
        $seconds = (int) ($this->dashboard_list_poll_seconds ?: 8);

        return max(5, min(300, $seconds));
    }

    public function setAlert(?string $code, ?string $message = null): void
    {
        $this->forceFill([
            'last_alert' => $code,
            'last_alert_message' => $message,
            'last_alert_at' => $code ? now() : null,
        ])->save();
    }

    public function clearAlert(?string $code = null): void
    {
        if ($code !== null && $this->last_alert !== $code) {
            return;
        }

        $this->setAlert(null, null);
    }
}
