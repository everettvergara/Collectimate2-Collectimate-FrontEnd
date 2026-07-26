<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->string('service_base_url')->default('http://127.0.0.1:8080/api/v1');
            $table->string('api_key')->nullable();
            $table->string('callback_base_url')->nullable();
            $table->boolean('auto_start_enabled')->default(false);
            $table->string('service_exe_path')->nullable();
            $table->string('config_json_path')->nullable();
            $table->boolean('auto_detect_at_ports')->default(true);
            $table->json('http_ports_to_test')->nullable();
            $table->timestamp('service_started_at')->nullable();
            $table->unsignedBigInteger('sms_sent_since_start')->default(0);
            $table->string('last_alert')->nullable();
            $table->text('last_alert_message')->nullable();
            $table->timestamp('last_alert_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};
