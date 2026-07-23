<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['group' => 'company', 'key' => 'company_name', 'value' => 'Collectimate'],
            ['group' => 'company', 'key' => 'company_address', 'value' => ''],
            ['group' => 'company', 'key' => 'company_phone', 'value' => ''],
            ['group' => 'company', 'key' => 'company_email', 'value' => ''],
            ['group' => 'general', 'key' => 'timezone', 'value' => 'Asia/Manila'],
            ['group' => 'general', 'key' => 'date_format', 'value' => 'Y-m-d'],
            ['group' => 'lookups', 'key' => 'contact_info_types', 'value' => 'email,phone,landline,fax,other'],
            ['group' => 'lookups', 'key' => 'address_types', 'value' => 'home,work,billing,other'],
            ['group' => 'lookups', 'key' => 'social_platforms', 'value' => 'linkedin,facebook,x,instagram,website,other'],
        ];

        foreach ($defaults as $row) {
            Setting::query()->updateOrCreate(
                ['key' => $row['key']],
                ['group' => $row['group'], 'value' => $row['value']],
            );
        }
    }
}
