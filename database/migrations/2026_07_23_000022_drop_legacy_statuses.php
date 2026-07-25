<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite cannot DROP COLUMN while a foreign-key definition still references it.
            // Keep empty legacy tables so accounts.status_id FK remains valid in tests.
            if (Schema::hasTable('status_histories')) {
                DB::table('status_histories')->delete();
            }
            if (Schema::hasTable('statuses')) {
                DB::table('statuses')->delete();
            }

            return;
        }

        if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'status_id')) {
            Schema::table('accounts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('status_id');
            });
        }

        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('statuses');
    }

    public function down(): void
    {
        // Irreversible drop of legacy global statuses.
    }
};
