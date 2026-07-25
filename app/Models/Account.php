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
        'account_name',
        'product',
        'balance',
        'due_date',
        'date_acquired',
        'external_reference',
        'entity_status_id',
        'entity_action_code_id',
        'assigned_agent_profile_id',
        'notes',
        'custom_fields',
        'last_reference_amount',
        'last_reference_date',
        'last_reference_time',
        'last_reference_text',
        'last_reference_contact_info_id',
        'last_reference_address_id',
        'last_activity_user_id',
        'last_activity_agent_profile_id',
        'last_activity_type_id',
        'activities_count',
        'last_activity_at',
        'positive_activity_count',
        'negative_activity_count',
        'neutral_activity_count',
        'sms_out_count',
        'sms_in_count',
        'call_success_count',
        'call_failed_count',
        'call_total_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'due_date' => 'date',
            'date_acquired' => 'date',
            'custom_fields' => 'array',
            'last_reference_amount' => 'decimal:2',
            'last_reference_date' => 'date',
            'last_activity_at' => 'datetime',
            'activities_count' => 'integer',
            'positive_activity_count' => 'integer',
            'negative_activity_count' => 'integer',
            'neutral_activity_count' => 'integer',
            'sms_out_count' => 'integer',
            'sms_in_count' => 'integer',
            'call_success_count' => 'integer',
            'call_failed_count' => 'integer',
            'call_total_count' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function entityStatus(): BelongsTo
    {
        return $this->belongsTo(EntityStatus::class);
    }

    public function entityActionCode(): BelongsTo
    {
        return $this->belongsTo(EntityActionCode::class);
    }

    public function assignedAgentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'assigned_agent_profile_id');
    }

    public function lastActivityUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_activity_user_id');
    }

    public function lastActivityAgentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'last_activity_agent_profile_id');
    }

    public function lastActivityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'last_activity_type_id');
    }

    public function lastReferenceContactInfo(): BelongsTo
    {
        return $this->belongsTo(AccountContactInfo::class, 'last_reference_contact_info_id');
    }

    public function lastReferenceAddress(): BelongsTo
    {
        return $this->belongsTo(AccountAddress::class, 'last_reference_address_id');
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

    public function activities(): HasMany
    {
        return $this->hasMany(AccountActivity::class)->orderByDesc('occurred_at');
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
