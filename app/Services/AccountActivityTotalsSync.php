<?php

namespace App\Services;

use App\Enums\ActionCodeClassification;
use App\Models\Account;
use App\Models\ActivityType;
use Illuminate\Support\Facades\DB;

class AccountActivityTotalsSync
{
    public function sync(Account $account): void
    {
        $totals = DB::table('account_activities')
            ->where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as aggregate')
            ->first();

        $lastNonSystemAt = DB::table('account_activities')
            ->join('activity_types', 'activity_types.id', '=', 'account_activities.activity_type_id')
            ->where('account_activities.account_id', $account->id)
            ->whereNull('account_activities.deleted_at')
            ->where('activity_types.code', '!=', 'system')
            ->max('account_activities.occurred_at');

        $classificationCounts = DB::table('account_activities')
            ->where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->selectRaw('classification, COUNT(*) as aggregate')
            ->groupBy('classification')
            ->pluck('aggregate', 'classification');

        $byCode = DB::table('account_activities')
            ->join('activity_types', 'activity_types.id', '=', 'account_activities.activity_type_id')
            ->where('account_activities.account_id', $account->id)
            ->whereNull('account_activities.deleted_at')
            ->selectRaw('activity_types.code, COUNT(*) as aggregate')
            ->groupBy('activity_types.code')
            ->pluck('aggregate', 'code');

        $smsOut = (int) ($byCode['sms_send'] ?? 0);
        $smsIn = (int) ($byCode['sms_receive'] ?? 0);
        $callSuccess = 0;
        foreach (ActivityType::SUCCESS_CODES as $code) {
            $callSuccess += (int) ($byCode[$code] ?? 0);
        }
        $callFailed = 0;
        foreach (ActivityType::FAILED_CODES as $code) {
            $callFailed += (int) ($byCode[$code] ?? 0);
        }

        $rawTotal = (int) ($totals->aggregate ?? 0);
        $systemCount = (int) ($byCode['system'] ?? 0);
        $incomingCount = 0;
        foreach (ActivityType::INCOMING_CODES as $code) {
            $incomingCount += (int) ($byCode[$code] ?? 0);
        }

        $activitiesCount = max(0, $rawTotal - $incomingCount);
        $nonSystemCount = max(0, $rawTotal - $systemCount - $incomingCount);

        $account->forceFill([
            'activities_count' => $activitiesCount,
            'non_system_activities_count' => $nonSystemCount,
            'last_activity_at' => $lastNonSystemAt,
            'positive_activity_count' => (int) ($classificationCounts[ActionCodeClassification::Positive->value] ?? 0),
            'negative_activity_count' => (int) ($classificationCounts[ActionCodeClassification::Negative->value] ?? 0),
            'neutral_activity_count' => (int) ($classificationCounts[ActionCodeClassification::Neutral->value] ?? 0),
            'sms_out_count' => $smsOut,
            'sms_in_count' => $smsIn,
            'call_success_count' => $callSuccess,
            'call_failed_count' => $callFailed,
            'call_total_count' => $callSuccess + $callFailed,
        ])->save();
    }
}
