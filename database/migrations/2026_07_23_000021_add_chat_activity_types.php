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
            ['name' => 'Chat Send', 'code' => 'chat_send', 'sort_order' => 86],
            ['name' => 'Chat Receive', 'code' => 'chat_receive', 'sort_order' => 87],
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_types')) {
            return;
        }

        DB::table('activity_types')
            ->whereIn('code', ['chat_send', 'chat_receive'])
            ->update([
                'is_active' => false,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }
};
