<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CampaignScope
{
    public static function apply(Builder $builder, ?string $column = 'campaign_id'): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return;
        }

        $campaignIds = $user->allowedCampaignIds();

        if ($campaignIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereIn($column, $campaignIds);
    }

    /**
     * Scope Entity rows: visible when Entity has ≥1 assigned Campaign.
     */
    public static function applyToEntity(Builder $builder): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return;
        }

        $campaignIds = $user->allowedCampaignIds();

        if ($campaignIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereHas('campaigns', function (Builder $query) use ($campaignIds): void {
            $query->whereIn('campaigns.id', $campaignIds);
        });
    }

    /**
     * Scope rows that belong to an Entity (comments, history, files).
     */
    public static function applyViaEntity(Builder $builder): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return;
        }

        $campaignIds = $user->allowedCampaignIds();

        if ($campaignIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereHas('entity.campaigns', function (Builder $query) use ($campaignIds): void {
            $query->whereIn('campaigns.id', $campaignIds);
        });
    }

    /**
     * Scope rows that belong to an Account (contact info, addresses, etc.).
     */
    public static function applyViaAccount(Builder $builder): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return;
        }

        $campaignIds = $user->allowedCampaignIds();

        if ($campaignIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereHas('account', function (Builder $query) use ($campaignIds): void {
            $query->whereIn('campaign_id', $campaignIds);
        });
    }
}
