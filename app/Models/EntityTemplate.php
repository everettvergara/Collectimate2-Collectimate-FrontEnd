<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityTemplate extends Model
{
    protected $fillable = [
        'entity_id',
        'types',
        'slug',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
