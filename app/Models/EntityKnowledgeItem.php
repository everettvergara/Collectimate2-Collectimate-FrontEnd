<?php

namespace App\Models;

use App\Enums\KnowledgeItemType;
use App\Support\CampaignScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntityKnowledgeItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'entity_id',
        'entity_knowledge_group_id',
        'title',
        'type',
        'body',
        'url',
        'file_path',
        'file_disk',
        'original_name',
        'mime',
        'size',
        'sort_order',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => KnowledgeItemType::class,
            'size' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(EntityKnowledgeGroup::class, 'entity_knowledge_group_id');
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
