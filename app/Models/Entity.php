<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntityScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    use BelongsToEntityScope;
    use SoftDeletes;

    protected $fillable = [
        'entity_code',
        'name',
        'logo_path',
        'custom_fields',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->logo_path) {
                return null;
            }

            // Root-relative so the image works regardless of APP_URL / port
            // (e.g. php artisan serve on :8000 vs APP_URL=http://localhost).
            return '/storage/'.ltrim($this->logo_path, '/');
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function accounts(): HasManyThrough
    {
        return $this->hasManyThrough(Account::class, Campaign::class);
    }

    public function entityStatuses(): HasMany
    {
        return $this->hasMany(EntityStatus::class);
    }

    public function entityActionCodes(): HasMany
    {
        return $this->hasMany(EntityActionCode::class);
    }

    public function entityTemplates(): HasMany
    {
        return $this->hasMany(EntityTemplate::class);
    }

    public function knowledgeGroups(): HasMany
    {
        return $this->hasMany(EntityKnowledgeGroup::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(EntityKnowledgeItem::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(EntityFile::class);
    }

    public function ensureDefaultKnowledgeGroup(?int $actorId = null): EntityKnowledgeGroup
    {
        $existing = $this->knowledgeGroups()
            ->withoutGlobalScopes()
            ->where('is_default', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->knowledgeGroups()->create([
            'name' => 'Default',
            'code' => 'default',
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
            'is_default' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }
}
