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
            $table->unsignedInteger('activities_count')->default(0)->after('last_activity_type_id');
            $table->timestamp('last_activity_at')->nullable()->after('activities_count');

            $table->index(['campaign_id', 'deleted_at', 'id'], 'accounts_campaign_deleted_id_index');
            $table->index('last_activity_at');
        });

        DB::table('accounts')->update([
            'activities_count' => DB::raw('(
                SELECT COUNT(*) FROM account_activities
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
            )'),
            'last_activity_at' => DB::raw('(
                SELECT MAX(account_activities.occurred_at) FROM account_activities
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
            )'),
        ]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('accounts_campaign_deleted_id_index');
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn(['activities_count', 'last_activity_at']);
        });
    }
};
