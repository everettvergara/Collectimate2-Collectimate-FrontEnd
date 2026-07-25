<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedInteger('sms_out_count')->default(0)->after('neutral_activity_count');
            $table->unsignedInteger('sms_in_count')->default(0)->after('sms_out_count');
            $table->unsignedInteger('call_success_count')->default(0)->after('sms_in_count');
            $table->unsignedInteger('call_failed_count')->default(0)->after('call_success_count');
            $table->unsignedInteger('call_total_count')->default(0)->after('call_failed_count');
        });

        DB::table('accounts')->update([
            'sms_out_count' => DB::raw("(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code = 'sms_send'
            )"),
            'sms_in_count' => DB::raw("(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code = 'sms_receive'
            )"),
            'call_success_count' => DB::raw("(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code IN ('manual_call_success', 'robo_call_success')
            )"),
            'call_failed_count' => DB::raw("(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code IN ('manual_call_failed', 'robo_call_failed')
            )"),
            'call_total_count' => DB::raw("(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code IN (
                      'manual_call_success', 'robo_call_success',
                      'manual_call_failed', 'robo_call_failed'
                  )
            )"),
        ]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'sms_out_count',
                'sms_in_count',
                'call_success_count',
                'call_failed_count',
                'call_total_count',
            ]);
        });
    }
};
