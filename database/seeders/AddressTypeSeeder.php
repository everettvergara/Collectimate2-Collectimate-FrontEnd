<?php

namespace Database\Seeders;

use App\Models\AddressType;
use Illuminate\Database\Seeder;

class AddressTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Home', 'code' => 'home', 'sort_order' => 10, 'is_default' => true],
            ['name' => 'Office', 'code' => 'office', 'sort_order' => 20, 'is_default' => false],
        ];

        foreach ($defaults as $row) {
            AddressType::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                    'is_default' => $row['is_default'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
