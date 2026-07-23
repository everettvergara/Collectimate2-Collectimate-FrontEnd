<?php

namespace App\Models\Concerns;

use App\Support\CampaignScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCampaign
{
    protected static function bootBelongsToCampaign(): void
    {
        static::addGlobalScope('campaign', function (Builder $builder): void {
            CampaignScope::apply($builder);
        });
    }
}
