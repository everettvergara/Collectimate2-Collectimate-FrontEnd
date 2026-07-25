<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\AgentProfile;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = Campaign::query()->with(['entity:id,name,entity_code'])->withCount('accounts');

        if (! $user->isSuperAdmin()) {
            $query->whereIn('id', $user->allowedCampaignIds());
        }

        $campaigns = ListingQuery::paginate(
            $query,
            $request,
            ['campaign_code', 'name', 'description'],
            ['campaign_code', 'name', 'status', 'created_at', 'id'],
        );

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'create' => $user->hasPermission('campaigns.create'),
                'export' => $user->hasPermission('campaigns.export'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $prefillEntityId = $request->integer('entity_id') ?: null;

        if ($prefillEntityId) {
            $this->authorizeEntityAccess($request, $prefillEntityId);
        }

        return Inertia::render('Campaigns/Form', [
            'campaign' => null,
            'entities' => $this->availableEntities($request),
            'statuses' => array_column(CampaignStatus::cases(), 'value'),
            'prefillEntityId' => $prefillEntityId,
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeEntityAccess($request, (int) $data['entity_id']);

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $campaign = Campaign::query()->create($data);

        $auditLogger->log('campaign.created', $campaign, $campaign->id);

        return redirect()->route('entities.show', $campaign->entity_id)->with('success', 'Campaign created.');
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->loadCount(['agentProfiles']);
        $campaign->load([
            'entity:id,name,entity_code',
            'agentProfiles' => fn ($query) => $query
                ->orderBy('display_name')
                ->select(['agent_profiles.id', 'display_name', 'employee_number', 'position']),
        ]);

        $assignedIds = $campaign->agentProfiles->pluck('id');
        $availableAgents = AgentProfile::query()
            ->whereNotIn('id', $assignedIds)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'employee_number']);

        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign,
            'availableAgents' => $availableAgents,
            'can' => [
                'update' => $request->user()->hasPermission('campaigns.update'),
                'archive' => $request->user()->hasPermission('campaigns.archive'),
                'delete' => $request->user()->hasPermission('campaigns.delete'),
                'manageAssignments' => $request->user()->hasPermission('campaign_assignments.manage'),
            ],
        ]);
    }

    public function edit(Request $request, Campaign $campaign): Response
    {
        $this->authorizeCampaign($request, $campaign);

        return Inertia::render('Campaigns/Form', [
            'campaign' => $campaign->load('entity'),
            'entities' => $this->availableEntities($request),
            'statuses' => array_column(CampaignStatus::cases(), 'value'),
        ]);
    }

    public function update(Request $request, Campaign $campaign, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $data = $this->validated($request, $campaign);
        $this->authorizeEntityAccess($request, (int) $data['entity_id']);

        $data['updated_by'] = $request->user()->id;

        $campaign->update($data);

        $auditLogger->log('campaign.updated', $campaign, $campaign->id);

        return redirect()->route('entities.show', $campaign->entity_id)->with('success', 'Campaign updated.');
    }

    public function archive(Request $request, Campaign $campaign, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->update([
            'status' => CampaignStatus::Archived,
            'updated_by' => $request->user()->id,
        ]);

        $auditLogger->log('campaign.archived', $campaign, $campaign->id);

        return redirect()->route('campaigns.index')->with('success', 'Campaign archived.');
    }

    public function destroy(Request $request, Campaign $campaign, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $entityId = $campaign->entity_id;
        $campaignId = $campaign->id;
        $campaignName = $campaign->name;

        $campaign->forceDelete();

        $auditLogger->log('campaign.force_deleted', null, $campaignId, [
            'campaign_id' => $campaignId,
            'name' => $campaignName,
            'entity_id' => $entityId,
        ]);

        return redirect()->route('entities.show', $entityId)->with('success', 'Campaign deleted.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Campaign::query()->with('entity');

        if (! $user->isSuperAdmin()) {
            $query->whereIn('id', $user->allowedCampaignIds());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['campaign_code', 'name'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
                $builder->orWhereHas('entity', function ($entityQuery) use ($search): void {
                    $entityQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('entity_code', 'like', "%{$search}%");
                });
            });
        }

        $rows = $query->orderBy('id')->get()->map(fn (Campaign $campaign): array => [
            $campaign->campaign_code,
            $campaign->name,
            $campaign->entity?->name,
            $campaign->status?->value,
        ]);

        $auditLogger->log('campaigns.exported');

        return CsvExporter::download('campaigns.csv', [
            'Code', 'Name', 'Entity', 'Status',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Campaign $campaign = null): array
    {
        return $request->validate([
            'entity_id' => ['required', 'exists:entities,id'],
            'campaign_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('campaigns', 'campaign_code')->ignore($campaign?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ]);
    }

    private function availableEntities(Request $request)
    {
        return Entity::query()
            ->orderBy('name')
            ->get(['id', 'name', 'entity_code']);
    }

    private function authorizeEntityAccess(Request $request, int $entityId): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return;
        }

        $visible = Entity::query()->whereKey($entityId)->exists();

        if (! $visible) {
            abort(403);
        }
    }

    private function authorizeCampaign(Request $request, Campaign $campaign): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return;
        }

        if (! in_array($campaign->id, $user->allowedCampaignIds(), true)) {
            abort(403);
        }
    }
}
