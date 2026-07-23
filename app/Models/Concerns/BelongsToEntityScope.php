<?php

namespace App\Models\Concerns;

use App\Support\CampaignScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToEntityScope
{
    protected static function bootBelongsToEntityScope(): void
    {
        static::addGlobalScope('entity_campaign', function (Builder $builder): void {
            CampaignScope::applyToEntity($builder);
        });
    }
}
