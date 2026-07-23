<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use BelongsToCampaign;
    use SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'account_number',
        'product',
        'balance',
        'due_date',
        'external_reference',
        'status_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contactInfos(): HasMany
    {
        return $this->hasMany(AccountContactInfo::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(AccountAddress::class);
    }

    public function secondaryContacts(): HasMany
    {
        return $this->hasMany(AccountSecondaryContact::class);
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(AccountSocialLink::class);
    }
}
