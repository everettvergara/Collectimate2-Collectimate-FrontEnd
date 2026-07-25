<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->text('reference_text')->nullable()->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->text('last_reference_text')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('account_activities', function (Blueprint $table) {
            $table->string('reference_text')->nullable()->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('last_reference_text')->nullable()->change();
        });
    }
};
