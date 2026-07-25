<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('last_reference_contact_info_id')
                ->nullable()
                ->after('last_reference_text')
                ->constrained('account_contact_infos')
                ->nullOnDelete();

            $table->foreignId('last_reference_address_id')
                ->nullable()
                ->after('last_reference_contact_info_id')
                ->constrained('account_addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_reference_contact_info_id');
            $table->dropConstrainedForeignId('last_reference_address_id');
        });
    }
};
