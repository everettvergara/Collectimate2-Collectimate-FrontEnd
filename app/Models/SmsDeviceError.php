<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsDeviceError extends Model
{
    protected $fillable = [
        'runtime_device_id',
        'error_code',
        'error_message',
        'recommended_action',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
