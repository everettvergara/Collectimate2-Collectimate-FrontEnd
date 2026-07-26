<?php

namespace App\Models;

use App\Support\CampaignScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityKnowledgeGroup extends Model
{
    protected $fillable = [
        'entity_id',
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('campaign', function (Builder $builder): void {
            CampaignScope::applyViaEntity($builder);
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EntityKnowledgeItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
