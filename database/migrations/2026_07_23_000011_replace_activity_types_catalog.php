<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOCKED = [
        ['name' => 'System', 'code' => 'system', 'sort_order' => 10],
        ['name' => 'SMS Send', 'code' => 'sms_send', 'sort_order' => 20],
        ['name' => 'SMS Receive', 'code' => 'sms_receive', 'sort_order' => 30],
        ['name' => 'Manual Call Success', 'code' => 'manual_call_success', 'sort_order' => 40],
        ['name' => 'Manual Call Failed', 'code' => 'manual_call_failed', 'sort_order' => 50],
        ['name' => 'Robo Call Success', 'code' => 'robo_call_success', 'sort_order' => 60],
        ['name' => 'Robo Call Failed', 'code' => 'robo_call_failed', 'sort_order' => 70],
        ['name' => 'Email', 'code' => 'email', 'sort_order' => 80],
        ['name' => 'Skip', 'code' => 'skip', 'sort_order' => 90],
        ['name' => 'Field', 'code' => 'field', 'sort_order' => 100],
        ['name' => 'Others', 'code' => 'others', 'sort_order' => 110],
    ];

    /** @var array<string, string> */
    private const REMAP = [
        'remarks' => 'others',
        'account' => 'system',
        'sms' => 'sms_send',
        'call' => 'manual_call_success',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('activity_types')) {
            return;
        }

        $now = now();

        foreach (self::LOCKED as $row) {
            $existing = DB::table('activity_types')->where('code', $row['code'])->first();
            if ($existing) {
                DB::table('activity_types')->where('id', $existing->id)->update([
                    'name' => $row['name'],
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => $row['sort_order'],
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('activity_types')->insert([
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => $row['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('account_activities')) {
            foreach (self::REMAP as $fromCode => $toCode) {
                $fromId = DB::table('activity_types')->where('code', $fromCode)->value('id');
                $toId = DB::table('activity_types')->where('code', $toCode)->value('id');
                if ($fromId && $toId) {
                    DB::table('account_activities')
                        ->where('activity_type_id', $fromId)
                        ->update(['activity_type_id' => $toId, 'updated_at' => $now]);
                }
            }

            $lockedCodes = array_column(self::LOCKED, 'code');
            $accountsWithObsoleteLastType = DB::table('accounts')
                ->join('activity_types', 'activity_types.id', '=', 'accounts.last_activity_type_id')
                ->whereNotIn('activity_types.code', $lockedCodes)
                ->select('accounts.id', 'activity_types.code')
                ->get();

            foreach ($accountsWithObsoleteLastType as $row) {
                $mappedCode = self::REMAP[$row->code] ?? null;
                if (! $mappedCode) {
                    continue;
                }
                $newTypeId = DB::table('activity_types')->where('code', $mappedCode)->value('id');
                if ($newTypeId) {
                    DB::table('accounts')->where('id', $row->id)->update([
                        'last_activity_type_id' => $newTypeId,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $lockedCodes = array_column(self::LOCKED, 'code');
        DB::table('activity_types')
            ->whereNotIn('code', $lockedCodes)
            ->update([
                'is_active' => false,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Irreversible catalog replacement.
    }
};
