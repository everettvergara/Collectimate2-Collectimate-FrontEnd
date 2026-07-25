<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_types')) {
            return;
        }

        $now = now();
        $rows = [
            ['name' => 'Email Send', 'code' => 'email_send', 'sort_order' => 80],
            ['name' => 'Email Receive', 'code' => 'email_receive', 'sort_order' => 85],
        ];

        foreach ($rows as $row) {
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

        $fromId = DB::table('activity_types')->where('code', 'email')->value('id');
        $toId = DB::table('activity_types')->where('code', 'email_send')->value('id');

        if ($fromId && $toId && Schema::hasTable('account_activities')) {
            DB::table('account_activities')
                ->where('activity_type_id', $fromId)
                ->update(['activity_type_id' => $toId, 'updated_at' => $now]);

            if (Schema::hasColumn('accounts', 'last_activity_type_id')) {
                DB::table('accounts')
                    ->where('last_activity_type_id', $fromId)
                    ->update(['last_activity_type_id' => $toId, 'updated_at' => $now]);
            }
        }

        if ($fromId) {
            DB::table('activity_types')->where('id', $fromId)->update([
                'is_active' => false,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible split.
    }
};
