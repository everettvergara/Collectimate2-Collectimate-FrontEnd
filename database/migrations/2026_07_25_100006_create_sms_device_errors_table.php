<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_device_errors', function (Blueprint $table) {
            $table->id();
            $table->string('runtime_device_id')->index();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('recommended_action')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_device_errors');
    }
};
