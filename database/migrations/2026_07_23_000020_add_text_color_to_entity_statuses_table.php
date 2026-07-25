<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_statuses', function (Blueprint $table) {
            $table->string('text_color')->default('#ffffff')->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('entity_statuses', function (Blueprint $table) {
            $table->dropColumn('text_color');
        });
    }
};
