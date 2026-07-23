<?php

namespace App\Models;

use App\Enums\AgentProfileStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'display_name',
        'position',
        'department',
        'mobile',
        'email',
        'status',
        'notes',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentProfileStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function campaignAssignments(): HasMany
    {
        return $this->hasMany(CampaignAssignment::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_assignments')
            ->withTimestamps()
            ->withPivot('assigned_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
