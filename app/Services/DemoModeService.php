<?php

namespace App\Services;

use App\Enums\ActionCodeClassification;
use App\Enums\AgentProfileStatus;
use App\Enums\CampaignStatus;
use App\Enums\ContactInfoType;
use App\Enums\UserStatus;
use App\Models\ActivityType;
use App\Models\AgentProfile;
use App\Models\Campaign;
use App\Models\CampaignAssignment;
use App\Models\Entity;
use App\Models\EntityStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\TemplateCollectionsCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoModeService
{
    public const DEMO_ENTITIES = [
        ['entity_code' => 'FYD-COLLECTIONS', 'name' => 'FYD Collections', 'prefix' => 'FYD'],
        ['entity_code' => 'ABC-COLLECTIONS', 'name' => 'ABC Collections', 'prefix' => 'ABC'],
    ];

    public const ACCOUNTS_PER_COMMON_POOL = 1000;

    public const UPLOAD_REMARKS = 'Uploaded to common pool';

    public const DEMO_MOBILE_PRIMARY = '+639177101995';

    public const DEMO_MOBILE_SECONDARY = '+639673113799';

    /** @var list<string> */
    public const DEMO_USERNAMES = ['abc', 'fyd'];

    /**
     * @return array{template_ensured: bool, deleted_entities: int, deleted_demo_users: int}
     */
    public function clearNonTemplateData(?User $actor = null): array
    {
        $deleted = 0;

        Entity::withTrashed()
            ->where('entity_code', '!=', TemplateCollectionsCatalog::ENTITY_CODE)
            ->orderBy('id')
            ->each(function (Entity $entity) use (&$deleted): void {
                $entity->forceDelete();
                $deleted++;
            });

        $deletedDemoUsers = $this->clearDemoUsers();

        $template = $this->ensureTemplate($actor);

        return [
            'template_ensured' => $template !== null,
            'deleted_entities' => $deleted,
            'deleted_demo_users' => $deletedDemoUsers,
        ];
    }

    /**
     * Remove demo agent users (abc / fyd) and their agent profiles.
     * Admin is never removed.
     */
    private function clearDemoUsers(): int
    {
        $deleted = 0;

        User::query()
            ->whereIn('username', self::DEMO_USERNAMES)
            ->orderBy('id')
            ->each(function (User $user) use (&$deleted): void {
                AgentProfile::withTrashed()
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->each(function (AgentProfile $profile): void {
                        $profile->forceDelete();
                    });

                $user->delete();
                $deleted++;
            });

        return $deleted;
    }

    /**
     * @return array{
     *     entities: list<array{
     *         entity_code: string,
     *         name: string,
     *         campaigns_ensured: int,
     *         statuses_copied: int,
     *         actions_copied: int,
     *         templates_copied: int,
     *         accounts_created: int,
     *         accounts_skipped: bool
     *     }>,
     *     message: string
     * }
     */
    public function createDemoData(User $actor): array
    {
        $template = $this->ensureTemplate($actor);
        if (! $template) {
            return [
                'entities' => [],
                'message' => 'Template entity could not be created.',
            ];
        }

        $results = [];
        $skippedAny = false;

        foreach (self::DEMO_ENTITIES as $demo) {
            $results[] = DB::transaction(function () use ($demo, $template, $actor, &$skippedAny) {
                $entity = Entity::withTrashed()->updateOrCreate(
                    ['entity_code' => $demo['entity_code']],
                    [
                        'name' => $demo['name'],
                        'deleted_at' => null,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ],
                );

                $copy = TemplateCollectionsCatalog::copyCatalogsTo($template, $entity);

                $campaignsEnsured = 0;
                foreach (TemplateCollectionsCatalog::statuses() as $status) {
                    Campaign::withTrashed()->updateOrCreate(
                        ['campaign_code' => $demo['prefix'].'-'.$status['code']],
                        [
                            'entity_id' => $entity->id,
                            'name' => $status['name'],
                            'description' => null,
                            'status' => CampaignStatus::Active,
                            'deleted_at' => null,
                            'created_by' => $actor->id,
                            'updated_by' => $actor->id,
                        ],
                    );
                    $campaignsEnsured++;
                }

                $commonPool = Campaign::query()
                    ->where('entity_id', $entity->id)
                    ->where('campaign_code', $demo['prefix'].'-CP')
                    ->firstOrFail();

                $cpStatusId = EntityStatus::query()
                    ->where('entity_id', $entity->id)
                    ->where('code', 'CP')
                    ->value('id');

                $existingCount = DB::table('accounts')
                    ->where('campaign_id', $commonPool->id)
                    ->whereNull('deleted_at')
                    ->count();

                $accountsCreated = 0;
                $accountsSkipped = $existingCount > 0;

                if ($accountsSkipped) {
                    $skippedAny = true;
                } else {
                    $accountsCreated = $this->seedCommonPoolAccounts(
                        $commonPool->id,
                        $cpStatusId ? (int) $cpStatusId : null,
                        $actor,
                        $demo['prefix'],
                    );
                }

                return [
                    'entity_code' => $demo['entity_code'],
                    'name' => $demo['name'],
                    'campaigns_ensured' => $campaignsEnsured,
                    'statuses_copied' => $copy['statuses_copied'],
                    'actions_copied' => $copy['actions_copied'],
                    'templates_copied' => $copy['templates_copied'],
                    'accounts_created' => $accountsCreated,
                    'accounts_skipped' => $accountsSkipped,
                ];
            });
        }

        $agents = $this->ensureDemoAgents($actor);
        $this->syncDemoCampaignAssignments($actor, $agents);

        $message = 'Demo entities created. Agents assigned: admin (all demo campaigns), abc (ABC), fyd (FYD). Logins: admin / abc / fyd (password).';
        if ($skippedAny) {
            $message .= ' Some Common Pool campaigns already had accounts — clear demo data first to reseed 1,000 accounts.';
        }

        return [
            'entities' => $results,
            'message' => $message,
        ];
    }

    /**
     * @return array{admin: ?AgentProfile, abc: AgentProfile, fyd: AgentProfile}
     */
    private function ensureDemoAgents(User $actor): array
    {
        $agentRoleId = Role::query()->where('slug', 'agent')->firstOrFail()->id;

        $adminUser = User::query()->where('username', 'admin')->first();
        $adminAgent = $adminUser
            ? AgentProfile::query()->where('user_id', $adminUser->id)->first()
            : null;

        $abcUser = User::query()->updateOrCreate(
            ['username' => 'abc'],
            [
                'first_name' => 'ABC',
                'last_name' => 'Agent',
                'name' => 'ABC Agent',
                'email' => 'abc@collectimate.local',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'role_id' => $agentRoleId,
                'email_verified_at' => now(),
            ],
        );

        $abcAgent = AgentProfile::query()->updateOrCreate(
            ['user_id' => $abcUser->id],
            [
                'employee_number' => 'EMP-ABC',
                'first_name' => 'ABC',
                'last_name' => 'Agent',
                'display_name' => 'ABC Agent',
                'position' => 'Collections Agent',
                'department' => 'Collections',
                'email' => $abcUser->email,
                'status' => AgentProfileStatus::Active,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $fydUser = User::query()->updateOrCreate(
            ['username' => 'fyd'],
            [
                'first_name' => 'FYD',
                'last_name' => 'Agent',
                'name' => 'FYD Agent',
                'email' => 'fyd@collectimate.local',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'role_id' => $agentRoleId,
                'email_verified_at' => now(),
            ],
        );

        $fydAgent = AgentProfile::query()->updateOrCreate(
            ['user_id' => $fydUser->id],
            [
                'employee_number' => 'EMP-FYD',
                'first_name' => 'FYD',
                'last_name' => 'Agent',
                'display_name' => 'FYD Agent',
                'position' => 'Collections Agent',
                'department' => 'Collections',
                'email' => $fydUser->email,
                'status' => AgentProfileStatus::Active,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        return [
            'admin' => $adminAgent,
            'abc' => $abcAgent,
            'fyd' => $fydAgent,
        ];
    }

    /**
     * @param  array{admin: ?AgentProfile, abc: AgentProfile, fyd: AgentProfile}  $agents
     */
    private function syncDemoCampaignAssignments(User $actor, array $agents): void
    {
        $demoCodes = array_column(self::DEMO_ENTITIES, 'entity_code');

        $campaigns = Campaign::query()
            ->whereHas('entity', fn ($query) => $query->whereIn('entity_code', $demoCodes))
            ->with('entity:id,entity_code')
            ->get(['id', 'entity_id']);

        foreach ($campaigns as $campaign) {
            $entityCode = $campaign->entity?->entity_code;

            if ($agents['admin']) {
                CampaignAssignment::query()->firstOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'agent_profile_id' => $agents['admin']->id,
                    ],
                    [
                        'assigned_by' => $actor->id,
                    ],
                );
            }

            if ($entityCode === 'ABC-COLLECTIONS') {
                CampaignAssignment::query()->firstOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'agent_profile_id' => $agents['abc']->id,
                    ],
                    [
                        'assigned_by' => $actor->id,
                    ],
                );
            }

            if ($entityCode === 'FYD-COLLECTIONS') {
                CampaignAssignment::query()->firstOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'agent_profile_id' => $agents['fyd']->id,
                    ],
                    [
                        'assigned_by' => $actor->id,
                    ],
                );
            }
        }
    }

    /**
     * @return array{
     *     template: array{id: int, entity_code: string, name: string, status_count: int, action_count: int}|null,
     *     demo_entities: list<array{id: int, entity_code: string, name: string, campaign_count: int, common_pool_accounts: int}>
     * }
     */
    public function summary(): array
    {
        $template = Entity::query()
            ->where('entity_code', TemplateCollectionsCatalog::ENTITY_CODE)
            ->first();

        $templatePayload = null;
        if ($template) {
            $templatePayload = [
                'id' => $template->id,
                'entity_code' => $template->entity_code,
                'name' => $template->name,
                'status_count' => $template->entityStatuses()->count(),
                'action_count' => $template->entityActionCodes()->count(),
            ];
        }

        $demoCodes = array_column(self::DEMO_ENTITIES, 'entity_code');
        $demoEntities = Entity::query()
            ->whereIn('entity_code', $demoCodes)
            ->orderBy('entity_code')
            ->get()
            ->map(function (Entity $entity) {
                $prefix = str_starts_with($entity->entity_code, 'FYD') ? 'FYD' : 'ABC';
                $commonPoolId = Campaign::query()
                    ->where('entity_id', $entity->id)
                    ->where('campaign_code', $prefix.'-CP')
                    ->value('id');

                return [
                    'id' => $entity->id,
                    'entity_code' => $entity->entity_code,
                    'name' => $entity->name,
                    'campaign_count' => $entity->campaigns()->count(),
                    'common_pool_accounts' => $commonPoolId
                        ? (int) DB::table('accounts')->where('campaign_id', $commonPoolId)->whereNull('deleted_at')->count()
                        : 0,
                ];
            })
            ->all();

        return [
            'template' => $templatePayload,
            'demo_entities' => $demoEntities,
        ];
    }

    public function ensureTemplate(?User $actor = null): ?Entity
    {
        $userId = $actor?->id
            ?? User::query()->where('username', 'admin')->value('id')
            ?? User::query()->orderBy('id')->value('id');

        $entity = Entity::withTrashed()->updateOrCreate(
            ['entity_code' => TemplateCollectionsCatalog::ENTITY_CODE],
            [
                'name' => TemplateCollectionsCatalog::ENTITY_NAME,
                'deleted_at' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );

        TemplateCollectionsCatalog::applyToEntity($entity);

        return $entity->fresh();
    }

    private function seedCommonPoolAccounts(
        int $campaignId,
        ?int $entityStatusId,
        User $actor,
        string $prefix,
    ): int {
        $systemTypeId = ActivityType::query()
            ->where('code', 'system')
            ->where('is_active', true)
            ->value('id');

        $actor->loadMissing('agentProfile');
        $agentProfileId = $actor->agentProfile?->id;
        $now = now();
        $products = ['Personal Loan', 'Credit Card', 'Auto Loan', 'Home Loan', 'Salary Loan'];
        $firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Liza', 'Carlos', 'Rosa', 'Miguel', 'Elena'];
        $lastNames = ['Santos', 'Reyes', 'Cruz', 'Garcia', 'Lopez', 'Torres', 'Flores', 'Rivera', 'Gomez', 'Diaz'];

        $total = self::ACCOUNTS_PER_COMMON_POOL;
        $chunkSize = 100;
        $streets = ['Rizal Street', 'Mabini Avenue', 'Quezon Boulevard', 'Luna Street', 'Bonifacio Drive', 'Osmena Road'];
        $cities = [
            ['city' => 'Quezon City', 'state' => 'Metro Manila', 'postal_code' => '1100'],
            ['city' => 'Makati', 'state' => 'Metro Manila', 'postal_code' => '1200'],
            ['city' => 'Manila', 'state' => 'Metro Manila', 'postal_code' => '1000'],
            ['city' => 'Pasig', 'state' => 'Metro Manila', 'postal_code' => '1600'],
            ['city' => 'Cebu City', 'state' => 'Cebu', 'postal_code' => '6000'],
            ['city' => 'Davao City', 'state' => 'Davao del Sur', 'postal_code' => '8000'],
        ];

        for ($offset = 0; $offset < $total; $offset += $chunkSize) {
            $accountRows = [];
            $batchCount = min($chunkSize, $total - $offset);

            for ($i = 0; $i < $batchCount; $i++) {
                $n = $offset + $i + 1;
                $balance = number_format(mt_rand(1500, 250000) + (mt_rand(0, 99) / 100), 2, '.', '');
                $originalTotal = number_format(
                    (float) $balance * (1 + (mt_rand(5, 40) / 100)) + (mt_rand(0, 99) / 100),
                    2,
                    '.',
                    '',
                );
                $dueDateCarbon = $now->copy()->subDays(mt_rand(0, 180))->startOfDay();
                $dueDate = $dueDateCarbon->toDateString();
                $daysPastDue = (int) $dueDateCarbon->diffInDays($now->copy()->startOfDay());
                $gender = ['Male', 'Female'][mt_rand(0, 1)];

                $accountRows[] = [
                    'campaign_id' => $campaignId,
                    'account_number' => sprintf('%s-CP-%06d', $prefix, $n),
                    'account_name' => $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)],
                    'product' => $products[array_rand($products)],
                    'balance' => $balance,
                    'due_date' => $dueDate,
                    'date_acquired' => $now->copy()->subDays(mt_rand(0, 30))->toDateString(),
                    'entity_status_id' => $entityStatusId,
                    'notes' => null,
                    'custom_fields' => json_encode([
                        'balance' => $balance,
                        'due_date' => $dueDate,
                        'original_total_amount' => $originalTotal,
                        'days_past_due' => $daysPastDue,
                        'gender' => $gender,
                    ], JSON_THROW_ON_ERROR),
                    'activities_count' => $systemTypeId ? 1 : 0,
                    'non_system_activities_count' => 0,
                    'last_activity_at' => null,
                    'neutral_activity_count' => $systemTypeId ? 1 : 0,
                    'positive_activity_count' => 0,
                    'negative_activity_count' => 0,
                    'sms_out_count' => 0,
                    'sms_in_count' => 0,
                    'call_success_count' => 0,
                    'call_failed_count' => 0,
                    'call_total_count' => 0,
                    'last_activity_type_id' => null,
                    'last_activity_user_id' => null,
                    'last_activity_agent_profile_id' => null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('accounts')->insert($accountRows);

            $numbers = array_column($accountRows, 'account_number');
            $inserted = DB::table('accounts')
                ->where('campaign_id', $campaignId)
                ->whereIn('account_number', $numbers)
                ->pluck('id');

            $activityRows = [];
            $contactRows = [];
            $addressRows = [];

            foreach ($inserted as $accountId) {
                if ($systemTypeId) {
                    $activityRows[] = [
                        'account_id' => $accountId,
                        'occurred_at' => $now,
                        'activity_type_id' => $systemTypeId,
                        'actor_user_id' => $actor->id,
                        'agent_profile_id' => $agentProfileId,
                        'assigned_agent_profile_id' => null,
                        'entity_status_id' => $entityStatusId,
                        'entity_action_code_id' => null,
                        'classification' => ActionCodeClassification::Neutral->value,
                        'remarks' => self::UPLOAD_REMARKS,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $contactRows[] = [
                    'account_id' => $accountId,
                    'type' => ContactInfoType::Mobile->value,
                    'name' => null,
                    'relationship' => null,
                    'value' => self::DEMO_MOBILE_PRIMARY,
                    'label' => null,
                    'is_primary' => true,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $contactRows[] = [
                    'account_id' => $accountId,
                    'type' => ContactInfoType::Mobile->value,
                    'name' => 'Secondary',
                    'relationship' => 'Self',
                    'value' => self::DEMO_MOBILE_SECONDARY,
                    'label' => null,
                    'is_primary' => false,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $place = $cities[array_rand($cities)];
                $addressRows[] = [
                    'account_id' => $accountId,
                    'type' => 'home',
                    'name' => null,
                    'relationship' => null,
                    'line1' => mt_rand(1, 999).' '.$streets[array_rand($streets)],
                    'line2' => null,
                    'city' => $place['city'],
                    'state' => $place['state'],
                    'postal_code' => $place['postal_code'],
                    'country' => 'Philippines',
                    'is_primary' => true,
                    'remarks' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($activityRows !== []) {
                DB::table('account_activities')->insert($activityRows);
            }
            if ($contactRows !== []) {
                DB::table('account_contact_infos')->insert($contactRows);
            }
            if ($addressRows !== []) {
                DB::table('account_addresses')->insert($addressRows);
            }
        }

        return $total;
    }
}
