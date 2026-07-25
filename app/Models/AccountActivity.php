<?php

namespace App\Models;

use App\Enums\ActionCodeClassification;
use App\Support\CampaignScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountActivity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'occurred_at',
        'activity_type_id',
        'actor_user_id',
        'agent_profile_id',
        'assigned_agent_profile_id',
        'entity_status_id',
        'entity_action_code_id',
        'entity_template_id',
        'classification',
        'reference_amount',
        'reference_date',
        'reference_time',
        'reference_text',
        'reference_contact_info_id',
        'reference_address_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'classification' => ActionCodeClassification::class,
            'reference_amount' => 'decimal:2',
            'reference_date' => 'date',
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

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class);
    }

    public function assignedAgentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'assigned_agent_profile_id');
    }

    public function entityStatus(): BelongsTo
    {
        return $this->belongsTo(EntityStatus::class);
    }

    public function entityActionCode(): BelongsTo
    {
        return $this->belongsTo(EntityActionCode::class);
    }

    public function entityTemplate(): BelongsTo
    {
        return $this->belongsTo(EntityTemplate::class);
    }

    public function referenceContactInfo(): BelongsTo
    {
        return $this->belongsTo(AccountContactInfo::class, 'reference_contact_info_id');
    }

    public function referenceAddress(): BelongsTo
    {
        return $this->belongsTo(AccountAddress::class, 'reference_address_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AccountActivityFile::class);
    }
}
