<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCallbackEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'response_type',
        'device_id',
        'payload',
        'event_timestamp',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'event_timestamp' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
