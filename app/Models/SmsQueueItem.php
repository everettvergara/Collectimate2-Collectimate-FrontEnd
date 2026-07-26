<?php

namespace App\Models;

use App\Enums\SmsQueueItemStatus;
use App\Enums\SmsTargetMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SmsQueueItem extends Model
{
    protected $fillable = [
        'sms_batch_id',
        'account_id',
        'account_activity_id',
        'recipient',
        'message',
        'reference',
        'assigned_sms_device_id',
        'runtime_device_id',
        'target_mode',
        'target_sms_device_group_id',
        'target_sms_device_id',
        'status',
        'error_code',
        'error_message',
        'last_event_id',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SmsQueueItemStatus::class,
            'target_mode' => SmsTargetMode::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->reference) {
                $item->reference = (string) Str::uuid();
            }
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SmsBatch::class, 'sms_batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function accountActivity(): BelongsTo
    {
        return $this->belongsTo(AccountActivity::class);
    }

    public function assignedDevice(): BelongsTo
    {
        return $this->belongsTo(SmsDevice::class, 'assigned_sms_device_id');
    }

    public function targetGroup(): BelongsTo
    {
        return $this->belongsTo(SmsDeviceGroup::class, 'target_sms_device_group_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(SmsDevice::class, 'target_sms_device_id');
    }
}
