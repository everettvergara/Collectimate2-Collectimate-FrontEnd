<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string, permissions: list<string>|string}>
     */
    private array $roles = [
        'super-administrator' => [
            'name' => 'Super Administrator',
            'description' => 'Full access; bypasses campaign filters.',
            'permissions' => '*',
        ],
        'administrator' => [
            'name' => 'Administrator',
            'description' => 'System, user, and campaign administration.',
            'permissions' => [
                'users.view', 'users.create', 'users.update', 'users.delete', 'users.export',
                'agent_profiles.view', 'agent_profiles.create', 'agent_profiles.update', 'agent_profiles.delete', 'agent_profiles.export',
                'campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.archive', 'campaigns.delete', 'campaigns.export',
                'campaign_assignments.manage',
                'entities.view', 'entities.create', 'entities.update', 'entities.delete', 'entities.export',
                'accounts.view', 'accounts.create', 'accounts.update', 'accounts.delete', 'accounts.export', 'accounts.purge',
                'comments.view', 'comments.create', 'comments.update', 'comments.delete', 'comments.export',
                'files.view', 'files.create', 'files.delete', 'files.export',
                'activity_types.view', 'activity_types.export',
                'contact_types.view', 'contact_types.export',
                'address_types.view', 'address_types.manage', 'address_types.export',
                'reports.view', 'reports.export',
                'imports.run',
                'audit_logs.view', 'audit_logs.export',
                'settings.manage',
                'demo_mode.manage',
                'sms.view', 'sms.manage', 'sms.export', 'sms.queue.cancel',
            ],
        ],
        'supervisor' => [
            'name' => 'Supervisor',
            'description' => 'Oversees campaign work and assignments.',
            'permissions' => [
                'agent_profiles.view', 'agent_profiles.export',
                'campaigns.view', 'campaigns.export', 'campaigns.delete',
                'campaign_assignments.manage',
                'entities.view', 'entities.create', 'entities.update', 'entities.delete', 'entities.export',
                'accounts.view', 'accounts.create', 'accounts.update', 'accounts.delete', 'accounts.export',
                'comments.view', 'comments.create', 'comments.update', 'comments.delete', 'comments.export',
                'files.view', 'files.create', 'files.delete', 'files.export',
                'activity_types.view', 'activity_types.export',
                'contact_types.view', 'contact_types.export',
                'address_types.view', 'address_types.export',
                'reports.view', 'reports.export',
                'imports.run',
                'sms.view', 'sms.export', 'sms.queue.cancel',
            ],
        ],
        'agent' => [
            'name' => 'Agent',
            'description' => 'Day-to-day CRM on assigned campaigns.',
            'permissions' => [
                'campaigns.view',
                'entities.view', 'entities.create', 'entities.update', 'entities.export',
                'accounts.view', 'accounts.create', 'accounts.update', 'accounts.export',
                'comments.view', 'comments.create', 'comments.update',
                'files.view', 'files.create',
                'activity_types.view',
                'contact_types.view',
                'address_types.view',
                'reports.view',
                'sms.view',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Read-oriented access with selective export.',
            'permissions' => [
                'campaigns.view', 'campaigns.export',
                'entities.view', 'entities.export',
                'accounts.view', 'accounts.export',
                'comments.view', 'comments.export',
                'files.view', 'files.export',
                'activity_types.view', 'activity_types.export',
                'contact_types.view', 'contact_types.export',
                'address_types.view', 'address_types.export',
                'reports.view', 'reports.export',
            ],
        ],
    ];

    /**
     * @var array<string, array{module: string, name: string}>
     */
    private array $permissions = [
        'users.view' => ['module' => 'users', 'name' => 'View Users'],
        'users.create' => ['module' => 'users', 'name' => 'Create Users'],
        'users.update' => ['module' => 'users', 'name' => 'Update Users'],
        'users.delete' => ['module' => 'users', 'name' => 'Delete Users'],
        'users.export' => ['module' => 'users', 'name' => 'Export Users'],
        'agent_profiles.view' => ['module' => 'agent_profiles', 'name' => 'View Agent Profiles'],
        'agent_profiles.create' => ['module' => 'agent_profiles', 'name' => 'Create Agent Profiles'],
        'agent_profiles.update' => ['module' => 'agent_profiles', 'name' => 'Update Agent Profiles'],
        'agent_profiles.delete' => ['module' => 'agent_profiles', 'name' => 'Delete Agent Profiles'],
        'agent_profiles.export' => ['module' => 'agent_profiles', 'name' => 'Export Agent Profiles'],
        'campaigns.view' => ['module' => 'campaigns', 'name' => 'View Campaigns'],
        'campaigns.create' => ['module' => 'campaigns', 'name' => 'Create Campaigns'],
        'campaigns.update' => ['module' => 'campaigns', 'name' => 'Update Campaigns'],
        'campaigns.archive' => ['module' => 'campaigns', 'name' => 'Archive Campaigns'],
        'campaigns.delete' => ['module' => 'campaigns', 'name' => 'Delete Campaigns'],
        'campaigns.export' => ['module' => 'campaigns', 'name' => 'Export Campaigns'],
        'campaign_assignments.manage' => ['module' => 'campaign_assignments', 'name' => 'Manage Campaign Assignments'],
        'entities.view' => ['module' => 'entities', 'name' => 'View Entities'],
        'entities.create' => ['module' => 'entities', 'name' => 'Create Entities'],
        'entities.update' => ['module' => 'entities', 'name' => 'Update Entities'],
        'entities.delete' => ['module' => 'entities', 'name' => 'Delete Entities'],
        'entities.export' => ['module' => 'entities', 'name' => 'Export Entities'],
        'accounts.view' => ['module' => 'accounts', 'name' => 'View Accounts'],
        'accounts.create' => ['module' => 'accounts', 'name' => 'Create Accounts'],
        'accounts.update' => ['module' => 'accounts', 'name' => 'Update Accounts'],
        'accounts.delete' => ['module' => 'accounts', 'name' => 'Delete Accounts'],
        'accounts.export' => ['module' => 'accounts', 'name' => 'Export Accounts'],
        'accounts.purge' => ['module' => 'accounts', 'name' => 'Purge Accounts'],
        'comments.view' => ['module' => 'comments', 'name' => 'View Comments'],
        'comments.create' => ['module' => 'comments', 'name' => 'Create Comments'],
        'comments.update' => ['module' => 'comments', 'name' => 'Update Comments'],
        'comments.delete' => ['module' => 'comments', 'name' => 'Delete Comments'],
        'comments.export' => ['module' => 'comments', 'name' => 'Export Comments'],
        'files.view' => ['module' => 'files', 'name' => 'View Files'],
        'files.create' => ['module' => 'files', 'name' => 'Create Files'],
        'files.delete' => ['module' => 'files', 'name' => 'Delete Files'],
        'files.export' => ['module' => 'files', 'name' => 'Export Files'],
        'activity_types.view' => ['module' => 'activity_types', 'name' => 'View Activity Types'],
        'activity_types.export' => ['module' => 'activity_types', 'name' => 'Export Activity Types'],
        'contact_types.view' => ['module' => 'contact_types', 'name' => 'View Contact Types'],
        'contact_types.export' => ['module' => 'contact_types', 'name' => 'Export Contact Types'],
        'address_types.view' => ['module' => 'address_types', 'name' => 'View Address Types'],
        'address_types.manage' => ['module' => 'address_types', 'name' => 'Manage Address Types'],
        'address_types.export' => ['module' => 'address_types', 'name' => 'Export Address Types'],
        'reports.view' => ['module' => 'reports', 'name' => 'View Reports'],
        'reports.export' => ['module' => 'reports', 'name' => 'Export Reports'],
        'imports.run' => ['module' => 'imports', 'name' => 'Run Imports'],
        'audit_logs.view' => ['module' => 'audit_logs', 'name' => 'View Audit Logs'],
        'audit_logs.export' => ['module' => 'audit_logs', 'name' => 'Export Audit Logs'],
        'settings.manage' => ['module' => 'settings', 'name' => 'Manage Settings'],
        'demo_mode.manage' => ['module' => 'demo_mode', 'name' => 'Manage Demo Mode'],
        'sms.view' => ['module' => 'sms', 'name' => 'View SMS Dashboard & Callbacks'],
        'sms.manage' => ['module' => 'sms', 'name' => 'Manage SMS Configuration'],
        'sms.export' => ['module' => 'sms', 'name' => 'Export SMS Callbacks'],
        'sms.queue.cancel' => ['module' => 'sms', 'name' => 'Cancel SMS Queue Batches'],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach ($this->permissions as $slug => $data) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'module' => $data['module'],
                ],
            );

            $permissionIds[$slug] = $permission->id;
        }

        // Drop removed permission slugs so stale rows are not left attached to roles.
        Permission::query()
            ->whereNotIn('slug', array_keys($this->permissions))
            ->whereIn('slug', [
                'statuses.view',
                'statuses.manage',
                'statuses.export',
                'activity_types.manage',
            ])
            ->each(function (Permission $permission): void {
                $permission->roles()->detach();
                $permission->delete();
            });

        foreach ($this->roles as $slug => $data) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                ],
            );

            $slugs = $data['permissions'] === '*'
                ? array_keys($this->permissions)
                : $data['permissions'];

            $rolePermissionIds = array_map(
                fn (string $permissionSlug): int => $permissionIds[$permissionSlug],
                $slugs,
            );

            $role->permissions()->sync($rolePermissionIds);
        }
    }
}
