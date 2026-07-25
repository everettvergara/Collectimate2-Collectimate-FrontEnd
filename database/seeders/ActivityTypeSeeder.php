<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'System', 'code' => 'system', 'sort_order' => 10],
            ['name' => 'SMS Send', 'code' => 'sms_send', 'sort_order' => 20],
            ['name' => 'SMS Receive', 'code' => 'sms_receive', 'sort_order' => 30],
            ['name' => 'Manual Call Success', 'code' => 'manual_call_success', 'sort_order' => 40],
            ['name' => 'Manual Call Failed', 'code' => 'manual_call_failed', 'sort_order' => 50],
            ['name' => 'Robo Call Success', 'code' => 'robo_call_success', 'sort_order' => 60],
            ['name' => 'Robo Call Failed', 'code' => 'robo_call_failed', 'sort_order' => 70],
            ['name' => 'Email Send', 'code' => 'email_send', 'sort_order' => 80],
            ['name' => 'Email Receive', 'code' => 'email_receive', 'sort_order' => 85],
            ['name' => 'Chat Send', 'code' => 'chat_send', 'sort_order' => 86],
            ['name' => 'Chat Receive', 'code' => 'chat_receive', 'sort_order' => 87],
            ['name' => 'Skip', 'code' => 'skip', 'sort_order' => 90],
            ['name' => 'Field', 'code' => 'field', 'sort_order' => 100],
            ['name' => 'Others', 'code' => 'others', 'sort_order' => 110],
        ];

        foreach ($defaults as $row) {
            ActivityType::withTrashed()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => $row['sort_order'],
                    'deleted_at' => null,
                ],
            );
        }

        ActivityType::query()
            ->whereNotIn('code', ActivityType::LOCKED_CODES)
            ->update(['is_active' => false]);
    }
}
