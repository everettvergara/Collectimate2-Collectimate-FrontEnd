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
            $table->unsignedInteger('non_system_activities_count')->default(0)->after('activities_count');
        });

        DB::table('accounts')->update([
            'non_system_activities_count' => DB::raw('(
                SELECT COUNT(*) FROM account_activities
                INNER JOIN activity_types ON activity_types.id = account_activities.activity_type_id
                WHERE account_activities.account_id = accounts.id
                  AND account_activities.deleted_at IS NULL
                  AND activity_types.code != \'system\'
            )'),
        ]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('non_system_activities_count');
        });
    }
};
