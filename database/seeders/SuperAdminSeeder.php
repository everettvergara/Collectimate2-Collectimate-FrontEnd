<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('slug', 'super-administrator')->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'name' => 'Super Admin',
                'email' => 'admin@collectimate.local',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'role_id' => $role->id,
                'email_verified_at' => now(),
            ],
        );

        AgentProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_number' => 'EMP-0001',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'display_name' => 'Super Admin',
                'position' => 'System Administrator',
                'department' => 'IT',
                'email' => $user->email,
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );
    }
}
