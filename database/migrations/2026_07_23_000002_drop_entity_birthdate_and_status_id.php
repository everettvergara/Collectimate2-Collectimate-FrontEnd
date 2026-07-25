<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entities')) {
            return;
        }

        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'status_id')) {
                $table->dropConstrainedForeignId('status_id');
            }

            if (Schema::hasColumn('entities', 'birthdate')) {
                $table->dropColumn('birthdate');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('entities')) {
            return;
        }

        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'birthdate')) {
                $table->date('birthdate')->nullable()->after('name');
            }

            if (! Schema::hasColumn('entities', 'status_id')) {
                $table->foreignId('status_id')->nullable()->after('custom_fields')->constrained('statuses')->nullOnDelete();
                $table->index('status_id');
            }
        });
    }
};
