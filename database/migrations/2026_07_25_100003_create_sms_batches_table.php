<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_profile_id')->nullable()->constrained('agent_profiles')->nullOnDelete();
            $table->foreignId('account_activity_id')->nullable()->constrained('account_activities')->nullOnDelete();
            $table->text('message_body')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('queued')->default(0);
            $table->unsignedInteger('sending')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('cancelled')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_batches');
    }
};
