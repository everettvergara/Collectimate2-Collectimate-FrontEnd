<?php

namespace Database\Seeders;

use App\Models\SmsDevice;
use App\Models\SmsSetting;
use Illuminate\Database\Seeder;

class SmsSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = SmsSetting::defaults();

        $settings = SmsSetting::query()->first();

        if ($settings) {
            $settings->fill([
                ...$defaults,
                'auto_start_enabled' => false,
            ])->save();
        } else {
            SmsSetting::query()->create($defaults);
        }

        SmsDevice::ensureDefaultDemo();
    }
}
