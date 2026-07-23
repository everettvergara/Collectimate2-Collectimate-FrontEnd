<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Status;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityController extends Controller
{
    public function index(Request $request): Response
    {
        $entities = ListingQuery::paginate(
            Entity::query()->with(['status:id,name,color'])->withCount('campaigns'),
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
            'statuses' => Status::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $entity = Entity::query()->create($data);

        $auditLogger->log('entity.created', $entity);

        return redirect()->route('entities.index')->with('success', 'Entity created.');
    }

    public function show(Entity $entity): Response
    {
        $entity->load(['status', 'campaigns' => fn ($query) => $query->orderBy('name')]);

        return Inertia::render('Entities/Show', [
            'entity' => $entity,
            'can' => [
                'update' => request()->user()->hasPermission('entities.update'),
            ],
        ]);
    }

    public function edit(Entity $entity): Response
    {
        return Inertia::render('Entities/Form', [
            'entity' => $entity,
            'statuses' => Status::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $entity);

        $data['updated_by'] = $request->user()->id;

        $entity->update($data);

        $auditLogger->log('entity.updated', $entity);

        return redirect()->route('entities.index')->with('success', 'Entity updated.');
    }

    public function destroy(Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $entity->delete();

        $auditLogger->log('entity.deleted', $entity);

        return redirect()->route('entities.index')->with('success', 'Entity deleted.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = Entity::query()->with('status')->withCount('campaigns');

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
            $entity->status?->name,
        ]);

        $auditLogger->log('entities.exported');

        return CsvExporter::download('entities.csv', [
            'Code', 'Name', 'Campaigns', 'Status',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Entity $entity = null): array
    {
        return $request->validate([
            'entity_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('entities', 'entity_code')->ignore($entity?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'status_id' => ['nullable', 'exists:statuses,id'],
        ]);
    }
}
