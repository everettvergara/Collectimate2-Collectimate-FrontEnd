<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'color',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function statusHistoriesFrom(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'from_status_id');
    }

    public function statusHistoriesTo(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'to_status_id');
    }
}
