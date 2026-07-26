<?php

namespace App\Models;

use App\Enums\SmsReceivedAssociationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsReceivedMessage extends Model
{
    protected $fillable = [
        'sms_callback_event_id',
        'event_type',
        'sender',
        'message',
        'device_id',
        'received_at',
        'account_id',
        'account_contact_info_id',
        'account_activity_id',
        'association_status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'association_status' => SmsReceivedAssociationStatus::class,
        ];
    }

    public function callbackEvent(): BelongsTo
    {
        return $this->belongsTo(SmsCallbackEvent::class, 'sms_callback_event_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contactInfo(): BelongsTo
    {
        return $this->belongsTo(AccountContactInfo::class, 'account_contact_info_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(AccountActivity::class, 'account_activity_id');
    }
}
