<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class SmsDeviceGroup extends Model
{
    public const DEFAULT_NAME = 'Default';

    protected $fillable = [
        'name',
        'enabled',
        'sort_order',
        'rr_last_device_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function ensureDefault(): self
    {
        $group = static::query()->where('name', self::DEFAULT_NAME)->first();

        if ($group) {
            return $group;
        }

        return static::query()->create([
            'name' => self::DEFAULT_NAME,
            'enabled' => true,
            'sort_order' => 0,
            'rr_last_device_id' => null,
        ]);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(SmsDevice::class, 'sms_device_group_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function rrLastDevice(): BelongsTo
    {
        return $this->belongsTo(SmsDevice::class, 'rr_last_device_id');
    }

    /**
     * Next enabled device in ring order after rr_last_device_id whose runtime id is available.
     *
     * @param  Collection<int, string>|list<string>  $availableRuntimeIds
     */
    public function nextRoundRobinDevice(Collection|array $availableRuntimeIds): ?SmsDevice
    {
        $available = collect($availableRuntimeIds)
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->values()
            ->all();

        if ($available === []) {
            return null;
        }

        $members = $this->devices()
            ->where('enabled', true)
            ->whereNotNull('runtime_device_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            return null;
        }

        $ids = $members->pluck('id')->all();
        $startIndex = 0;
        if ($this->rr_last_device_id) {
            $pos = array_search((int) $this->rr_last_device_id, array_map('intval', $ids), true);
            if ($pos !== false) {
                $startIndex = ($pos + 1) % count($ids);
            }
        }

        $count = $members->count();
        for ($i = 0; $i < $count; $i++) {
            /** @var SmsDevice $candidate */
            $candidate = $members[($startIndex + $i) % $count];
            $runtimeId = (string) $candidate->runtime_device_id;
            if (in_array($runtimeId, $available, true)) {
                return $candidate;
            }
        }

        return null;
    }

    public function advanceRoundRobinCursor(?SmsDevice $device): void
    {
        if (! $device) {
            return;
        }

        $this->forceFill(['rr_last_device_id' => $device->id])->save();
    }
}
