<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('assigned_agent_profile_id')
                ->nullable()
                ->after('entity_action_code_id')
                ->constrained('agent_profiles')
                ->nullOnDelete();

            $table->decimal('last_reference_amount', 15, 2)->nullable()->after('custom_fields');
            $table->date('last_reference_date')->nullable()->after('last_reference_amount');
            $table->time('last_reference_time')->nullable()->after('last_reference_date');
            $table->string('last_reference_text')->nullable()->after('last_reference_time');
            $table->foreignId('last_activity_user_id')
                ->nullable()
                ->after('last_reference_text')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('last_activity_agent_profile_id')
                ->nullable()
                ->after('last_activity_user_id')
                ->constrained('agent_profiles')
                ->nullOnDelete();

        });

        Schema::table('account_activities', function (Blueprint $table) {
            $table->decimal('reference_amount', 15, 2)->nullable()->after('entity_action_code_id');
            $table->date('reference_date')->nullable()->after('reference_amount');
            $table->time('reference_time')->nullable()->after('reference_date');
            $table->string('reference_text')->nullable()->after('reference_time');
        });
    }

    public function down(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->dropColumn(['reference_amount', 'reference_date', 'reference_time', 'reference_text']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_activity_agent_profile_id');
            $table->dropConstrainedForeignId('last_activity_user_id');
            $table->dropColumn([
                'last_reference_amount',
                'last_reference_date',
                'last_reference_time',
                'last_reference_text',
            ]);
            $table->dropConstrainedForeignId('assigned_agent_profile_id');
        });
    }
};
