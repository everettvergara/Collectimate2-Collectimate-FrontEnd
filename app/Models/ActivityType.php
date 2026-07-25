<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityType extends Model
{
    use SoftDeletes;

    public const LOCKED_CODES = [
        'system',
        'sms_send',
        'sms_receive',
        'manual_call_success',
        'manual_call_failed',
        'robo_call_success',
        'robo_call_failed',
        'email_send',
        'email_receive',
        'chat_send',
        'chat_receive',
        'skip',
        'field',
        'others',
    ];

    public const INCOMING_CODES = [
        'sms_receive',
        'email_receive',
        'chat_receive',
    ];

    public const SUCCESS_CODES = [
        'manual_call_success',
        'robo_call_success',
    ];

    public const FAILED_CODES = [
        'manual_call_failed',
        'robo_call_failed',
    ];

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AccountActivity::class);
    }
}
