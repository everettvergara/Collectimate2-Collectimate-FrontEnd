<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * @var list<array{name: string, slug: string, category: string, color: string, sort_order: int}>
     */
    private array $statuses = [
        ['name' => 'New', 'slug' => 'new', 'category' => 'open', 'color' => '#3B82F6', 'sort_order' => 10],
        ['name' => 'Active', 'slug' => 'active', 'category' => 'open', 'color' => '#22C55E', 'sort_order' => 20],
        ['name' => 'Pending', 'slug' => 'pending', 'category' => 'open', 'color' => '#F59E0B', 'sort_order' => 30],
        ['name' => 'Promise To Pay', 'slug' => 'promise-to-pay', 'category' => 'open', 'color' => '#8B5CF6', 'sort_order' => 40],
        ['name' => 'Closed', 'slug' => 'closed', 'category' => 'closed', 'color' => '#6B7280', 'sort_order' => 50],
        ['name' => 'Skip Trace', 'slug' => 'skip-trace', 'category' => 'special', 'color' => '#EF4444', 'sort_order' => 60],
    ];

    public function run(): void
    {
        foreach ($this->statuses as $status) {
            Status::query()->updateOrCreate(
                ['slug' => $status['slug']],
                [
                    'name' => $status['name'],
                    'category' => $status['category'],
                    'color' => $status['color'],
                    'sort_order' => $status['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
