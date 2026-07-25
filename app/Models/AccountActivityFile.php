<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class AccountActivityFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_activity_id',
        'original_name',
        'path',
        'disk',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('campaign', function (Builder $builder): void {
            $user = Auth::user();

            if (! $user instanceof User || $user->isSuperAdmin()) {
                return;
            }

            $campaignIds = $user->allowedCampaignIds();

            if ($campaignIds === []) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->whereHas('activity.account', function (Builder $query) use ($campaignIds): void {
                $query->whereIn('campaign_id', $campaignIds);
            });
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(AccountActivity::class, 'account_activity_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
