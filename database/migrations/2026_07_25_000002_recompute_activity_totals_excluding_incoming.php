<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $incomingCodes = ['sms_receive', 'email_receive', 'chat_receive'];
        $incomingList = collect($incomingCodes)
            ->map(fn (string $code) => "'".str_replace("'", "''", $code)."'")
            ->implode(', ');

        DB::table('accounts')->update([
            'activities_count' => DB::raw("(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code NOT IN ({$incomingList})
            )"),
            'non_system_activities_count' => DB::raw("(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code != 'system'
                  AND activity_types.code NOT IN ({$incomingList})
            )"),
            'last_activity_at' => DB::raw("(
                SELECT MAX(account_activities.occurred_at) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code != 'system'
            )"),
        ]);

        $systemTypeId = DB::table('activity_types')->where('code', 'system')->value('id');
        if (! $systemTypeId) {
            return;
        }

        $accountsWithSystemLast = DB::table('accounts')
            ->where('last_activity_type_id', $systemTypeId)
            ->pluck('id');

        foreach ($accountsWithSystemLast as $accountId) {
            $latest = DB::table('account_activities')
                ->join('activity_types', 'activity_types.id', '=', 'account_activities.activity_type_id')
                ->where('account_activities.account_id', $accountId)
                ->whereNull('account_activities.deleted_at')
                ->where('activity_types.code', '!=', 'system')
                ->orderByDesc('account_activities.occurred_at')
                ->orderByDesc('account_activities.id')
                ->select([
                    'account_activities.activity_type_id',
                    'account_activities.actor_user_id',
                    'account_activities.agent_profile_id',
                ])
                ->first();

            DB::table('accounts')->where('id', $accountId)->update([
                'last_activity_type_id' => $latest?->activity_type_id,
                'last_activity_user_id' => $latest?->actor_user_id,
                'last_activity_agent_profile_id' => $latest?->agent_profile_id,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data recompute; prior semantics are not restored.
    }
};
