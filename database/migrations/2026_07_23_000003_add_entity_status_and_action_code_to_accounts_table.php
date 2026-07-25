<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('entity_status_id')
                ->nullable()
                ->after('status_id')
                ->constrained('entity_statuses')
                ->nullOnDelete();
            $table->foreignId('entity_action_code_id')
                ->nullable()
                ->after('entity_status_id')
                ->constrained('entity_action_codes')
                ->nullOnDelete();

            $table->index('entity_status_id');
            $table->index('entity_action_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_action_code_id');
            $table->dropConstrainedForeignId('entity_status_id');
        });
    }
};
