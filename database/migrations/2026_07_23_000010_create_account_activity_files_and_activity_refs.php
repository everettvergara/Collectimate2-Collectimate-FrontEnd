<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_activity_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_activity_id')->constrained('account_activities')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('account_activity_id');
        });

        Schema::table('account_activities', function (Blueprint $table) {
            $table->foreignId('reference_contact_info_id')
                ->nullable()
                ->after('reference_text')
                ->constrained('account_contact_infos')
                ->nullOnDelete();
            $table->foreignId('reference_address_id')
                ->nullable()
                ->after('reference_contact_info_id')
                ->constrained('account_addresses')
                ->nullOnDelete();
        });

        // Normalize free-text address types to seeded codes where possible.
        DB::table('account_addresses')
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('type')->orWhere('type', '')->orWhere('type', 'other');
            })
            ->update(['type' => 'home']);
    }

    public function down(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_address_id');
            $table->dropConstrainedForeignId('reference_contact_info_id');
        });

        Schema::dropIfExists('account_activity_files');
    }
};
