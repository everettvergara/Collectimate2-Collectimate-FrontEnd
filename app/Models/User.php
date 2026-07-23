<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'username',
    'name',
    'first_name',
    'last_name',
    'email',
    'mobile',
    'status',
    'role_id',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user->username) {
                $base = Str::slug(Str::before((string) $user->email, '@'), '_') ?: 'user';
                $candidate = $base;
                $suffix = 1;

                while (
                    static::query()
                        ->where('username', $candidate)
                        ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
                        ->exists()
                ) {
                    $candidate = "{$base}_{$suffix}";
                    $suffix++;
                }

                $user->username = $candidate;
            }

            if ($user->first_name || $user->last_name) {
                $user->name = trim("{$user->first_name} {$user->last_name}") ?: null;
            } elseif ($user->name && ! $user->first_name) {
                $user->first_name = $user->name;
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function agentProfile(): HasOne
    {
        return $this->hasOne(AgentProfile::class);
    }

    public function createdAgentProfiles(): HasMany
    {
        return $this->hasMany(AgentProfile::class, 'created_by');
    }

    public function updatedAgentProfiles(): HasMany
    {
        return $this->hasMany(AgentProfile::class, 'updated_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'imported_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === 'super-administrator';
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role?->permissions()->where('slug', $slug)->exists() ?? false;
    }

    /**
     * @return list<int>
     */
    public function allowedCampaignIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Campaign::query()->pluck('id')->all();
        }

        $agentProfile = $this->agentProfile;

        if (! $agentProfile) {
            return [];
        }

        return $agentProfile->campaignAssignments()
            ->pluck('campaign_id')
            ->all();
    }
}
