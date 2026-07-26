<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_device_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('rr_last_device_id')->nullable();
            $table->timestamps();
        });

        $defaultGroupId = DB::table('sms_device_groups')->insertGetId([
            'name' => 'Default',
            'enabled' => true,
            'sort_order' => 0,
            'rr_last_device_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('sms_devices', function (Blueprint $table) {
            $table->foreignId('sms_device_group_id')
                ->nullable()
                ->after('id')
                ->constrained('sms_device_groups')
                ->restrictOnDelete();
        });

        DB::table('sms_devices')->update(['sms_device_group_id' => $defaultGroupId]);

        DB::statement('ALTER TABLE sms_devices MODIFY sms_device_group_id BIGINT UNSIGNED NOT NULL');

        Schema::table('sms_device_groups', function (Blueprint $table) {
            $table->foreign('rr_last_device_id')
                ->references('id')
                ->on('sms_devices')
                ->nullOnDelete();
        });

        Schema::table('sms_queue_items', function (Blueprint $table) {
            $table->string('target_mode', 32)->nullable()->after('runtime_device_id');
            $table->foreignId('target_sms_device_group_id')
                ->nullable()
                ->after('target_mode')
                ->constrained('sms_device_groups')
                ->nullOnDelete();
            $table->foreignId('target_sms_device_id')
                ->nullable()
                ->after('target_sms_device_group_id')
                ->constrained('sms_devices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sms_queue_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_sms_device_id');
            $table->dropConstrainedForeignId('target_sms_device_group_id');
            $table->dropColumn('target_mode');
        });

        Schema::table('sms_device_groups', function (Blueprint $table) {
            $table->dropForeign(['rr_last_device_id']);
        });

        Schema::table('sms_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sms_device_group_id');
        });

        Schema::dropIfExists('sms_device_groups');
    }
};
