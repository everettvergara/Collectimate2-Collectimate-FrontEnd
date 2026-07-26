<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_knowledge_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 100)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entity_id', 'name']);
            $table->unique(['entity_id', 'code']);
            $table->index('entity_id');
        });

        Schema::create('entity_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('entity_knowledge_group_id')->constrained('entity_knowledge_groups')->restrictOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->longText('body')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_disk')->default('local');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('entity_id');
            $table->index('entity_knowledge_group_id');
            $table->index('type');
        });

        $now = now();
        $entityIds = DB::table('entities')->whereNull('deleted_at')->pluck('id');

        foreach ($entityIds as $entityId) {
            $exists = DB::table('entity_knowledge_groups')
                ->where('entity_id', $entityId)
                ->where('is_default', true)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('entity_knowledge_groups')->insert([
                'entity_id' => $entityId,
                'name' => 'Default',
                'code' => 'default',
                'description' => null,
                'sort_order' => 0,
                'is_active' => true,
                'is_default' => true,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_knowledge_items');
        Schema::dropIfExists('entity_knowledge_groups');
    }
};
