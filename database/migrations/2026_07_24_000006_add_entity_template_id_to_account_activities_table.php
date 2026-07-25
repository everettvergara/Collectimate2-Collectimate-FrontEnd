<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->foreignId('entity_template_id')
                ->nullable()
                ->after('entity_action_code_id')
                ->constrained('entity_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_template_id');
        });
    }
};
