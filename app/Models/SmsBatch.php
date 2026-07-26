<?php

namespace App\Models;

use App\Enums\SmsBatchSource;
use App\Enums\SmsBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsBatch extends Model
{
    protected $fillable = [
        'source',
        'created_by_user_id',
        'agent_profile_id',
        'account_activity_id',
        'message_body',
        'status',
        'priority',
        'total',
        'queued',
        'sending',
        'sent',
        'failed',
        'cancelled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source' => SmsBatchSource::class,
            'status' => SmsBatchStatus::class,
            'priority' => 'integer',
            'total' => 'integer',
            'queued' => 'integer',
            'sending' => 'integer',
            'sent' => 'integer',
            'failed' => 'integer',
            'cancelled' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SmsQueueItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class);
    }

    public function accountActivity(): BelongsTo
    {
        return $this->belongsTo(AccountActivity::class);
    }

    public function refreshCounts(): void
    {
        $counts = $this->items()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $queued = (int) ($counts['queued'] ?? 0);
        $sending = (int) ($counts['sending'] ?? 0);
        $sent = (int) ($counts['sent'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $cancelled = (int) ($counts['cancelled'] ?? 0);
        $total = $queued + $sending + $sent + $failed + $cancelled;

        $status = $this->status;
        if (! in_array($this->status, [SmsBatchStatus::Cancelled, SmsBatchStatus::Paused], true)) {
            if ($sending > 0 || ($queued > 0 && ($sent > 0 || $failed > 0))) {
                $status = SmsBatchStatus::Processing;
            } elseif ($queued > 0) {
                $status = SmsBatchStatus::Pending;
            } elseif ($failed > 0 && $sent === 0) {
                $status = SmsBatchStatus::Failed;
            } else {
                $status = SmsBatchStatus::Completed;
            }
        }

        $this->forceFill([
            'total' => $total,
            'queued' => $queued,
            'sending' => $sending,
            'sent' => $sent,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'status' => $status,
        ])->save();
    }
}
