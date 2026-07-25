<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\User;
use App\Support\TemplateCollectionsCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EntitySeeder extends Seeder
{
    public function run(): void
    {
        $this->wipeEntityTree();

        $adminId = User::query()->where('username', 'admin')->value('id')
            ?? User::query()->orderBy('id')->value('id');

        $entity = Entity::query()->create([
            'entity_code' => TemplateCollectionsCatalog::ENTITY_CODE,
            'name' => TemplateCollectionsCatalog::ENTITY_NAME,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        TemplateCollectionsCatalog::applyToEntity($entity);
    }

    private function wipeEntityTree(): void
    {
        Entity::withTrashed()
            ->orderBy('id')
            ->each(function (Entity $entity): void {
                $entity->forceDelete();
            });

        if (Schema::hasTable('import_batches')) {
            DB::table('import_batches')->delete();
        }

        // Hard-clear any orphan account rows that survived soft-delete parent paths.
        if (Schema::hasTable('accounts') && DB::table('accounts')->count() > 0) {
            DB::table('accounts')->delete();
        }
    }
}
