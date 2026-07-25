<?php

namespace App\Models;

use App\Enums\ActionCodeClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityActionCode extends Model
{
    protected $fillable = [
        'entity_id',
        'name',
        'code',
        'classification',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'classification' => ActionCodeClassification::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
