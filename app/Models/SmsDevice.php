<?php

namespace App\Models;

use App\Enums\SmsDeviceType;
use App\Services\Sms\SmsConfigJsonWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class SmsDevice extends Model
{
    public const DEFAULT_DEMO_RUNTIME_ID = 'SIM-DEMO';

    public const LEGACY_DEMO_RUNTIME_ID = 'DEMO-1';

    public const DEFAULT_DEMO_SEND_SUCCESS_RATE = 0.99;

    public const DEFAULT_DEMO_RECEIVE_INTERVAL_SECONDS = 300;

    protected $fillable = [
        'sms_device_group_id',
        'type',
        'name',
        'enabled',
        'sort_order',
        'config',
        'runtime_device_id',
        'health',
        'state',
        'last_error',
        'last_health_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => SmsDeviceType::class,
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'config' => 'array',
            'last_health_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SmsDeviceGroup::class, 'sms_device_group_id');
    }

    /**
     * Ensure an enabled Demo device always exists (C++ schema: id + type + port).
     */
    public static function ensureDefaultDemo(): self
    {
        $groupId = SmsDeviceGroup::ensureDefault()->id;

        $legacy = static::query()
            ->where('runtime_device_id', self::LEGACY_DEMO_RUNTIME_ID)
            ->first();

        $device = static::query()
            ->where('runtime_device_id', self::DEFAULT_DEMO_RUNTIME_ID)
            ->first();

        if (! $device && $legacy) {
            $device = $legacy;
            $device->runtime_device_id = self::DEFAULT_DEMO_RUNTIME_ID;
        }

        if (! $device) {
            $device = static::query()->create([
                'sms_device_group_id' => $groupId,
                'runtime_device_id' => self::DEFAULT_DEMO_RUNTIME_ID,
                'type' => SmsDeviceType::Demo,
                'name' => 'Demo',
                'enabled' => true,
                'sort_order' => 0,
                'config' => static::defaultDemoConfig(),
            ]);
            $created = true;
        } else {
            $created = false;
        }

        $dirty = $device->isDirty();
        if (! $device->sms_device_group_id) {
            $device->sms_device_group_id = $groupId;
            $dirty = true;
        }
        if (! $device->enabled) {
            $device->enabled = true;
            $dirty = true;
        }
        if ($device->type !== SmsDeviceType::Demo) {
            $device->type = SmsDeviceType::Demo;
            $dirty = true;
        }
        if ($device->name === '' || $device->name === null) {
            $device->name = 'Demo';
            $dirty = true;
        }
        if ($device->runtime_device_id !== self::DEFAULT_DEMO_RUNTIME_ID) {
            $device->runtime_device_id = self::DEFAULT_DEMO_RUNTIME_ID;
            $dirty = true;
        }

        $config = is_array($device->config) ? $device->config : [];
        $port = trim((string) ($config['port'] ?? $config['com_port'] ?? ''));
        if ($port === '') {
            $config['port'] = 'COM99';
            unset($config['com_port']);
            $dirty = true;
        } elseif (! isset($config['port']) && isset($config['com_port'])) {
            $config['port'] = $config['com_port'];
            unset($config['com_port']);
            $dirty = true;
        }

        $rate = $config['demo_send_success_rate'] ?? null;
        if (! is_numeric($rate) || (float) $rate < 0.0 || (float) $rate > 1.0) {
            $config['demo_send_success_rate'] = self::DEFAULT_DEMO_SEND_SUCCESS_RATE;
            $dirty = true;
        } else {
            $config['demo_send_success_rate'] = (float) $rate;
        }

        $interval = $config['demo_receive_interval_seconds'] ?? null;
        if (! is_numeric($interval) || (int) $interval < 1) {
            $config['demo_receive_interval_seconds'] = self::DEFAULT_DEMO_RECEIVE_INTERVAL_SECONDS;
            $dirty = true;
        } else {
            $config['demo_receive_interval_seconds'] = (int) $interval;
        }

        $device->config = $config;

        if ($dirty) {
            $device->save();
        }

        if ($created || $dirty) {
            try {
                $settings = SmsSetting::query()->first();
                if ($settings?->resolvedConfigJsonPath()) {
                    app(SmsConfigJsonWriter::class)->write($settings);
                }
            } catch (Throwable) {
                // Path/exe may not be installed yet; registry row is enough for Laravel UI.
            }
        }

        return $device->fresh() ?? $device;
    }

    /**
     * @return array{port: string, demo_send_success_rate: float, demo_receive_interval_seconds: int}
     */
    public static function defaultDemoConfig(): array
    {
        return [
            'port' => 'COM99',
            'demo_send_success_rate' => self::DEFAULT_DEMO_SEND_SUCCESS_RATE,
            'demo_receive_interval_seconds' => self::DEFAULT_DEMO_RECEIVE_INTERVAL_SECONDS,
        ];
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(SmsQueueItem::class, 'assigned_sms_device_id');
    }

    public function isHealthy(): bool
    {
        $health = strtolower((string) $this->health);
        $state = strtolower((string) $this->state);

        if (in_array($health, ['healthy', 'ok', 'good'], true)) {
            return true;
        }

        if (in_array($state, ['connected', 'ready', 'online'], true)
            && ($health === '' || in_array($health, ['healthy', 'ok', 'good'], true) || ! $this->last_error)) {
            return true;
        }

        // Unknown health with no error and empty/connected-ish state — treat as healthy for Demo/idle.
        if (($health === '' || $health === null)
            && ($state === '' || $state === null || in_array($state, ['connected', 'ready', 'online'], true))
            && ! $this->last_error) {
            return true;
        }

        return false;
    }
}
