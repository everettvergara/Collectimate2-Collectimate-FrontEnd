<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_batches', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(100)->after('status');
            $table->index(['priority', 'id']);
        });

        Schema::table('sms_settings', function (Blueprint $table) {
            $table->boolean('auto_device_recovery')->default(true)->after('auto_detect_at_ports');
        });
    }

    public function down(): void
    {
        Schema::table('sms_batches', function (Blueprint $table) {
            $table->dropIndex(['priority', 'id']);
            $table->dropColumn('priority');
        });

        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn('auto_device_recovery');
        });
    }
};
