<?php

namespace App\Http\Controllers;

use App\Enums\AgentProfileStatus;
use App\Models\Account;
use App\Models\AccountActivity;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityStatus;
use App\Models\User;
use App\Support\CampaignScope;
use App\Support\TemplateCollectionsCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $filters = [
            'entity_id' => $request->integer('entity_id') ?: null,
        ];

        return Inertia::render('Dashboard', [
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'activityToday' => $this->activityTodayByEntity($user),
            'portfolio' => $this->portfolioSummary($user, $filters),
            'agents' => $this->agentsSummary($user),
        ]);
    }

    /**
     * @return array{entities: Collection<int, Entity>}
     */
    private function filterOptions(): array
    {
        return [
            'entities' => $this->accessibleEntitiesQuery()
                ->orderBy('name')
                ->get(['id', 'name', 'entity_code']),
        ];
    }

    /**
     * Per accessible Entity: today’s account status counts by Campaign × Status.
     * One count per account (current entity_status_id) with last_activity_at today.
     *
     * @return list<array{
     *     entity: array{id: int, name: string, logo_url: ?string},
     *     statuses: list<array{id: int|string, name: string, color: ?string, text_color: ?string}>,
     *     rows: list<array{
     *         campaign_id: int,
     *         campaign_name: string,
     *         status_counts: array<string, int>,
     *         total: int
     *     }>,
     *     totals: array{status_counts: array<string, int>, total: int}
     * }>
     */
    private function activityTodayByEntity(User $user): array
    {
        $entities = $this->accessibleEntitiesQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'logo_path']);

        if ($entities->isEmpty()) {
            return [];
        }

        $allowedCampaignIds = $user->isSuperAdmin() ? null : $user->allowedCampaignIds();
        $startOfDay = now()->startOfDay();

        return $entities->map(function (Entity $entity) use ($allowedCampaignIds, $startOfDay) {
            $campaignQuery = Campaign::query()
                ->where('entity_id', $entity->id)
                ->orderBy('name');

            if ($allowedCampaignIds !== null) {
                $campaignQuery->whereIn('id', $allowedCampaignIds === [] ? [-1] : $allowedCampaignIds);
            }

            $campaigns = $campaignQuery->get(['id', 'name']);
            $campaignIds = $campaigns->pluck('id')->all();

            $statuses = EntityStatus::query()
                ->where('entity_id', $entity->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'text_color']);

            $aggregates = collect();
            if ($campaignIds !== []) {
                $aggregates = Account::query()
                    ->whereIn('campaign_id', $campaignIds)
                    ->where('last_activity_at', '>=', $startOfDay)
                    ->select('campaign_id', 'entity_status_id', DB::raw('count(*) as total'))
                    ->groupBy('campaign_id', 'entity_status_id')
                    ->get();
            }

            $countsByCampaign = [];
            foreach ($aggregates as $row) {
                $campaignId = (int) $row->campaign_id;
                $statusKey = $row->entity_status_id === null ? 'unassigned' : (string) $row->entity_status_id;
                $countsByCampaign[$campaignId][$statusKey] = (int) $row->total;
            }

            $hasUnassigned = $aggregates->contains(
                fn ($row) => $row->entity_status_id === null && (int) $row->total > 0,
            );

            $statusColumns = $statuses->map(fn (EntityStatus $status) => [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'text_color' => $status->text_color,
            ])->values()->all();

            if ($hasUnassigned) {
                $statusColumns[] = [
                    'id' => 'unassigned',
                    'name' => 'Unassigned',
                    'color' => null,
                    'text_color' => null,
                ];
            }

            $statusKeys = array_map(
                fn (array $column) => (string) $column['id'],
                $statusColumns,
            );

            $rows = [];
            $totalsByStatus = array_fill_keys($statusKeys, 0);
            $grandTotal = 0;

            foreach ($campaigns as $campaign) {
                $statusCounts = [];
                $rowTotal = 0;

                foreach ($statusKeys as $statusKey) {
                    $count = (int) ($countsByCampaign[$campaign->id][$statusKey] ?? 0);
                    $statusCounts[$statusKey] = $count;
                    $rowTotal += $count;
                }

                foreach ($countsByCampaign[$campaign->id] ?? [] as $statusKey => $count) {
                    if (! in_array((string) $statusKey, $statusKeys, true)) {
                        $rowTotal += (int) $count;
                    }
                }

                if ($rowTotal === 0) {
                    continue;
                }

                foreach ($statusKeys as $statusKey) {
                    $totalsByStatus[$statusKey] += $statusCounts[$statusKey];
                }

                $rows[] = [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'status_counts' => $statusCounts,
                    'total' => $rowTotal,
                ];

                $grandTotal += $rowTotal;
            }

            return [
                'entity' => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'logo_url' => $entity->logo_url,
                ],
                'statuses' => $statusColumns,
                'rows' => $rows,
                'totals' => [
                    'status_counts' => $totalsByStatus,
                    'total' => $grandTotal,
                ],
            ];
        })->values()->all();
    }

    /**
     * @param  array{entity_id: ?int}  $filters
     * @return array{
     *     entity: ?array{id: int, name: string},
     *     statuses: list<array{id: int|string, name: string, color: ?string, text_color: ?string}>,
     *     rows: list<array{
     *         entity_name: string,
     *         campaign_id: int,
     *         campaign_name: string,
     *         status_counts: array<string, int>,
     *         total: int
     *     }>,
     *     totals: array{status_counts: array<string, int>, total: int}
     * }
     */
    private function portfolioSummary(User $user, array $filters): array
    {
        $empty = [
            'entity' => null,
            'statuses' => [],
            'rows' => [],
            'totals' => [
                'status_counts' => [],
                'total' => 0,
            ],
        ];

        if (! $filters['entity_id']) {
            return $empty;
        }

        $entity = $this->accessibleEntitiesQuery()
            ->whereKey($filters['entity_id'])
            ->first(['id', 'name', 'entity_code']);

        if (! $entity) {
            return $empty;
        }

        $campaignQuery = Campaign::query()
            ->where('entity_id', $entity->id)
            ->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $allowedCampaignIds = $user->allowedCampaignIds();
            $campaignQuery->whereIn('id', $allowedCampaignIds === [] ? [-1] : $allowedCampaignIds);
        }

        $campaigns = $campaignQuery->get(['id', 'name', 'entity_id']);

        $statuses = EntityStatus::query()
            ->where('entity_id', $entity->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'text_color']);

        $campaignIds = $campaigns->pluck('id')->all();
        $aggregates = collect();

        if ($campaignIds !== []) {
            $aggregates = Account::query()
                ->whereIn('campaign_id', $campaignIds)
                ->select('campaign_id', 'entity_status_id', DB::raw('count(*) as total'))
                ->groupBy('campaign_id', 'entity_status_id')
                ->get();
        }

        $countsByCampaign = [];
        foreach ($aggregates as $row) {
            $campaignId = (int) $row->campaign_id;
            $statusKey = $row->entity_status_id === null ? 'unassigned' : (string) $row->entity_status_id;
            $countsByCampaign[$campaignId][$statusKey] = (int) $row->total;
        }

        $hasUnassigned = $aggregates->contains(fn ($row) => $row->entity_status_id === null && (int) $row->total > 0);

        $statusColumns = $statuses->map(fn (EntityStatus $status) => [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'text_color' => $status->text_color,
        ])->values()->all();

        if ($hasUnassigned) {
            $statusColumns[] = [
                'id' => 'unassigned',
                'name' => 'Unassigned',
                'color' => null,
                'text_color' => null,
            ];
        }

        $statusKeys = array_map(
            fn (array $column) => (string) $column['id'],
            $statusColumns,
        );

        $rows = [];
        $totalsByStatus = array_fill_keys($statusKeys, 0);
        $grandTotal = 0;

        foreach ($campaigns as $campaign) {
            $statusCounts = [];
            $rowTotal = 0;

            foreach ($statusKeys as $statusKey) {
                $count = (int) ($countsByCampaign[$campaign->id][$statusKey] ?? 0);
                $statusCounts[$statusKey] = $count;
                $totalsByStatus[$statusKey] += $count;
                $rowTotal += $count;
            }

            // Include any orphan status ids (inactive/deleted) in the row total only.
            foreach ($countsByCampaign[$campaign->id] ?? [] as $statusKey => $count) {
                if (! in_array((string) $statusKey, $statusKeys, true)) {
                    $rowTotal += (int) $count;
                }
            }

            $rows[] = [
                'entity_name' => $entity->name,
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'status_counts' => $statusCounts,
                'total' => $rowTotal,
            ];

            $grandTotal += $rowTotal;
        }

        return [
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
            ],
            'statuses' => $statusColumns,
            'rows' => $rows,
            'totals' => [
                'status_counts' => $totalsByStatus,
                'total' => $grandTotal,
            ],
        ];
    }

    private function accessibleEntitiesQuery(): Builder
    {
        $entitiesQuery = Entity::query()
            ->where('entity_code', '!=', TemplateCollectionsCatalog::ENTITY_CODE);

        CampaignScope::applyToEntity($entitiesQuery);

        return $entitiesQuery;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     avatar_url: ?string,
     *     presence: 'online'|'offline',
     *     last_activity_at: ?string,
     *     last_entity: ?string,
     *     last_campaign: ?string,
     *     last_account: ?string,
     *     last_comment: ?string,
     *     last_status: ?string,
     *     last_action: ?string
     * }>
     */
    private function agentsSummary(User $user): array
    {
        $profilesQuery = AgentProfile::query()
            ->with(['user:id,avatar_path'])
            ->where('status', AgentProfileStatus::Active)
            ->whereNotNull('user_id')
            ->orderBy('display_name')
            ->orderBy('first_name');

        if (! $user->isSuperAdmin()) {
            $allowedCampaignIds = $user->allowedCampaignIds();
            if ($allowedCampaignIds === []) {
                return [];
            }

            $profilesQuery->whereHas('campaignAssignments', function ($query) use ($allowedCampaignIds): void {
                $query->whereIn('campaign_id', $allowedCampaignIds);
            });
        }

        $profiles = $profilesQuery->get([
            'id',
            'user_id',
            'display_name',
            'first_name',
            'last_name',
        ]);

        if ($profiles->isEmpty()) {
            return [];
        }

        $userIds = $profiles->pluck('user_id')->filter()->unique()->values()->all();
        $profileIds = $profiles->pluck('id')->all();

        $onlineUserIds = AuditLog::query()
            ->where('action', 'login')
            ->whereIn('user_id', $userIds === [] ? [-1] : $userIds)
            ->where('created_at', '>=', now()->startOfDay())
            ->distinct()
            ->pluck('user_id')
            ->all();

        $onlineLookup = array_fill_keys($onlineUserIds, true);

        // Latest activity per agent: max occurred_at, then highest id on ties.
        $maxOccurred = AccountActivity::query()
            ->select('agent_profile_id', DB::raw('MAX(occurred_at) as max_occurred'))
            ->whereIn('agent_profile_id', $profileIds)
            ->groupBy('agent_profile_id');

        $activityIds = AccountActivity::query()
            ->select('account_activities.id', 'account_activities.agent_profile_id')
            ->joinSub($maxOccurred, 'latest_activity', function ($join): void {
                $join->on('account_activities.agent_profile_id', '=', 'latest_activity.agent_profile_id')
                    ->on('account_activities.occurred_at', '=', 'latest_activity.max_occurred');
            })
            ->orderByDesc('account_activities.id')
            ->get()
            ->unique('agent_profile_id')
            ->pluck('id')
            ->all();

        $activities = $activityIds === []
            ? collect()
            : AccountActivity::query()
                ->with([
                    'account:id,account_number,account_name,campaign_id',
                    'account.campaign:id,name,entity_id',
                    'account.campaign.entity:id,name',
                    'entityStatus:id,name',
                    'entityActionCode:id,name',
                ])
                ->whereIn('id', $activityIds)
                ->get()
                ->keyBy('agent_profile_id');

        $agents = $profiles->map(function (AgentProfile $profile) use ($onlineLookup, $activities) {
            $name = $profile->display_name
                ?: trim(implode(' ', array_filter([$profile->first_name, $profile->last_name])))
                ?: "Agent #{$profile->id}";

            $presence = isset($onlineLookup[$profile->user_id]) ? 'online' : 'offline';
            /** @var AccountActivity|null $activity */
            $activity = $activities->get($profile->id);
            $account = $activity?->account;
            $campaign = $account?->campaign;
            $entity = $campaign?->entity;

            $accountLabel = null;
            if ($account) {
                $accountLabel = $account->account_number
                    ?: ($account->account_name ?: "Account #{$account->id}");
                if ($account->account_number && $account->account_name) {
                    $accountLabel = $account->account_number.' — '.$account->account_name;
                }
            }

            return [
                'id' => $profile->id,
                'name' => $name,
                'avatar_url' => $profile->user?->avatar_url,
                'presence' => $presence,
                'last_activity_at' => $activity?->occurred_at?->toIso8601String(),
                'last_entity' => $entity?->name,
                'last_campaign' => $campaign?->name,
                'last_account' => $accountLabel,
                'last_comment' => filled($activity?->remarks) ? (string) $activity->remarks : null,
                'last_status' => $activity?->entityStatus?->name,
                'last_action' => $activity?->entityActionCode?->name,
            ];
        });

        return $agents
            ->sort(function (array $a, array $b): int {
                $presenceCmp = ($a['presence'] === 'online' ? 0 : 1) <=> ($b['presence'] === 'online' ? 0 : 1);
                if ($presenceCmp !== 0) {
                    return $presenceCmp;
                }

                $aTime = $a['last_activity_at'] ?? '';
                $bTime = $b['last_activity_at'] ?? '';

                if ($aTime === '' && $bTime === '') {
                    return strcasecmp($a['name'], $b['name']);
                }
                if ($aTime === '') {
                    return 1;
                }
                if ($bTime === '') {
                    return -1;
                }

                return strcmp($bTime, $aTime);
            })
            ->values()
            ->all();
    }
}
