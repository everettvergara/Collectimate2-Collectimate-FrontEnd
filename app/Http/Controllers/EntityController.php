<?php

namespace App\Http\Controllers;

use App\Enums\ActionCodeClassification;
use App\Enums\CampaignStatus;
use App\Enums\TemplateChannel;
use App\Models\Entity;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityController extends Controller
{
    public function index(Request $request): Response
    {
        $entities = ListingQuery::paginate(
            Entity::query()->withCount('campaigns'),
            $request,
            ['entity_code', 'name'],
            ['entity_code', 'name', 'created_at', 'id'],
        );

        return Inertia::render('Entities/Index', [
            'entities' => $entities,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'create' => $request->user()->hasPermission('entities.create'),
                'export' => $request->user()->hasPermission('entities.export'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Entities/Form', [
            'entity' => null,
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $entity = Entity::query()->create($data);

        $this->storeLogo($request, $entity);

        $auditLogger->log('entity.created', $entity);

        return redirect()->route('entities.show', $entity)->with('success', 'Entity created.');
    }

    public function show(Request $request, Entity $entity): Response
    {
        $entity->load([
            'campaigns' => fn ($query) => $query->withCount('accounts')->orderBy('name'),
            'entityStatuses' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'entityActionCodes' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'entityTemplates' => fn ($query) => $query->orderBy('slug'),
        ]);

        $canUpdate = $request->user()->hasPermission('entities.update');

        return Inertia::render('Entities/Show', [
            'entity' => $entity,
            'campaignStatuses' => array_column(CampaignStatus::cases(), 'value'),
            'actionCodeClassifications' => array_column(ActionCodeClassification::cases(), 'value'),
            'templateChannels' => array_column(TemplateChannel::cases(), 'value'),
            'copySources' => $canUpdate
                ? Entity::query()
                    ->whereKeyNot($entity->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'entity_code'])
                : [],
            'can' => [
                'update' => $canUpdate,
                'delete' => $request->user()->hasPermission('entities.delete'),
                'campaignsCreate' => $request->user()->hasPermission('campaigns.create'),
                'campaignsUpdate' => $request->user()->hasPermission('campaigns.update'),
                'campaignsDelete' => $request->user()->hasPermission('campaigns.delete'),
            ],
        ]);
    }

    public function edit(Entity $entity): Response
    {
        return Inertia::render('Entities/Form', [
            'entity' => $entity,
        ]);
    }

    public function update(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $entity);

        $data['updated_by'] = $request->user()->id;

        $entity->update($data);

        $this->storeLogo($request, $entity);

        $auditLogger->log('entity.updated', $entity);

        return redirect()->route('entities.show', $entity)->with('success', 'Entity updated.');
    }

    public function destroy(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $request->validate([
            'confirmation_name' => ['required', 'string'],
        ]);

        if ($request->string('confirmation_name')->toString() !== $entity->name) {
            return back()->withErrors([
                'confirmation_name' => 'Typed name does not match the entity name.',
            ]);
        }

        $entityId = $entity->id;
        $entityName = $entity->name;
        $logoPath = $entity->logo_path;

        DB::transaction(function () use ($entity): void {
            $entity->forceDelete();
        });

        if ($logoPath) {
            Storage::disk('public')->delete($logoPath);
        }

        $auditLogger->log('entity.force_deleted', null, null, [
            'entity_id' => $entityId,
            'name' => $entityName,
        ]);

        return redirect()->route('entities.index')->with('success', 'Entity deleted.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = Entity::query()->withCount('campaigns');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['entity_code', 'name'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderBy('id')->get()->map(fn (Entity $entity): array => [
            $entity->entity_code,
            $entity->name,
            $entity->campaigns_count,
        ]);

        $auditLogger->log('entities.exported');

        return CsvExporter::download('entities.csv', [
            'Code', 'Name', 'Campaigns',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Entity $entity = null): array
    {
        $data = $request->validate([
            'entity_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('entities', 'entity_code')->ignore($entity?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        unset($data['logo']);

        return $data;
    }

    private function storeLogo(Request $request, Entity $entity): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }

        if ($entity->logo_path) {
            Storage::disk('public')->delete($entity->logo_path);
        }

        $entity->logo_path = $request->file('logo')->store(
            'logos/'.$entity->id,
            'public',
        );
        $entity->save();
    }
}
