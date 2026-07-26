<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_batch_id')->constrained('sms_batches')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('account_activity_id')->nullable()->constrained('account_activities')->nullOnDelete();
            $table->string('recipient', 64)->nullable();
            $table->text('message');
            $table->uuid('reference')->unique();
            $table->foreignId('assigned_sms_device_id')->nullable()->constrained('sms_devices')->nullOnDelete();
            $table->string('runtime_device_id')->nullable()->index();
            $table->string('status', 32)->default('queued')->index();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('last_event_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_queue_items');
    }
};
