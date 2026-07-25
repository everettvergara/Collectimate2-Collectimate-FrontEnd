<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->string('classification')->default('neutral')->after('entity_action_code_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedInteger('positive_activity_count')->default(0)->after('last_activity_type_id');
            $table->unsignedInteger('negative_activity_count')->default(0)->after('positive_activity_count');
            $table->unsignedInteger('neutral_activity_count')->default(0)->after('negative_activity_count');
        });

        // Existing activities default to neutral; seed denormalized totals.
        DB::table('accounts')->update([
            'neutral_activity_count' => DB::raw('(
                SELECT COUNT(*) FROM account_activities
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
            )'),
            'positive_activity_count' => 0,
            'negative_activity_count' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->dropColumn('classification');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'positive_activity_count',
                'negative_activity_count',
                'neutral_activity_count',
            ]);
        });
    }
};
