<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_received_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_callback_event_id')->nullable()->unique()->constrained('sms_callback_events')->nullOnDelete();
            $table->string('event_type', 64)->default('SmsReceived');
            $table->string('sender')->index();
            $table->text('message')->nullable();
            $table->string('device_id')->nullable()->index();
            $table->timestamp('received_at')->nullable()->index();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('account_contact_info_id')->nullable()->constrained('account_contact_infos')->nullOnDelete();
            $table->foreignId('account_activity_id')->nullable()->constrained('account_activities')->nullOnDelete();
            $table->string('association_status', 32)->default('unmatched')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_received_messages');
    }
};
