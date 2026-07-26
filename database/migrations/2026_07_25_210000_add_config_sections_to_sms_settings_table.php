<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->json('config_service')->nullable()->after('http_ports_to_test');
            $table->json('config_logging')->nullable()->after('config_service');
            $table->json('config_http')->nullable()->after('config_logging');
            $table->json('config_callbacks')->nullable()->after('config_http');
            $table->json('config_queue')->nullable()->after('config_callbacks');
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn([
                'config_service',
                'config_logging',
                'config_http',
                'config_callbacks',
                'config_queue',
            ]);
        });
    }
};
