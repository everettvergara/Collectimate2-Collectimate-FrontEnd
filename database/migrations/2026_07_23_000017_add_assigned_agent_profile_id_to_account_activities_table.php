<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->foreignId('assigned_agent_profile_id')
                ->nullable()
                ->after('agent_profile_id')
                ->constrained('agent_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_agent_profile_id');
        });
    }
};
