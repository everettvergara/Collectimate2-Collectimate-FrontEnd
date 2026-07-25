<?php

namespace App\Models;

use App\Support\CampaignScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'type',
        'name',
        'relationship',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_primary',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
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
