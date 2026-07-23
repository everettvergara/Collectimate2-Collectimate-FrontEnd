<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?int $campaignId = null,
        ?array $metadata = null,
    ): void {
        $user = Auth::user();

        AuditLog::query()->create([
            'user_id' => $user?->id,
            'agent_profile_id' => $user?->agentProfile?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'campaign_id' => $campaignId,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?int $campaignId = null,
        ?array $metadata = null,
    ): void {
        app(self::class)->log($action, $subject, $campaignId, $metadata);
    }
}
