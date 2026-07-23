<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntityScope;
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
        'birthdate',
        'custom_fields',
        'status_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'custom_fields' => 'array',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
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

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(EntityFile::class);
    }
}
