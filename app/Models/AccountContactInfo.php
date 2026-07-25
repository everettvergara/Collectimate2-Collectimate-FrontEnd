<?php

namespace App\Models;

use App\Enums\ContactInfoType;
use App\Support\CampaignScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountContactInfo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'type',
        'name',
        'relationship',
        'value',
        'label',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactInfoType::class,
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('campaign', function (Builder $builder): void {
            CampaignScope::applyViaAccount($builder);
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
