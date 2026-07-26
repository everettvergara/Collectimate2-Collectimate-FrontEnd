<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeItemType;
use App\Models\Entity;
use App\Models\EntityKnowledgeGroup;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EntityKnowledgeGroupController extends Controller
{
    public function show(
        Request $request,
        Entity $entity,
        EntityKnowledgeGroup $entityKnowledgeGroup,
    ): Response {
        $this->ensureBelongsToEntity($entity, $entityKnowledgeGroup);

        $entityKnowledgeGroup->load([
            'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('title'),
        ]);

        return Inertia::render('Entities/KnowledgeGroupShow', [
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'entity_code' => $entity->entity_code,
                'logo_url' => $entity->logo_url,
            ],
            'group' => $entityKnowledgeGroup,
            'summary' => [
                'total' => $entityKnowledgeGroup->items->count(),
                'active' => $entityKnowledgeGroup->items->where('is_active', true)->count(),
            ],
            'knowledgeItemTypes' => array_column(KnowledgeItemType::cases(), 'value'),
            'can' => [
                'update' => $request->user()->hasPermission('entities.update'),
            ],
        ]);
    }

    public function store(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $entity);
        $data['entity_id'] = $entity->id;
        $data['is_default'] = false;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $group = EntityKnowledgeGroup::query()->create($data);

        $auditLogger->log('entity_knowledge_group.created', $group, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Knowledge group created.');
    }

    public function update(
        Request $request,
        Entity $entity,
        EntityKnowledgeGroup $entityKnowledgeGroup,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureBelongsToEntity($entity, $entityKnowledgeGroup);

        $data = $this->validated($request, $entity, $entityKnowledgeGroup);

        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $this->assertCanDeactivate($entity, $entityKnowledgeGroup);
        }

        $data['updated_by'] = $request->user()->id;
        unset($data['is_default']);

        $entityKnowledgeGroup->update($data);

        $auditLogger->log('entity_knowledge_group.updated', $entityKnowledgeGroup, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Knowledge group updated.');
    }

    public function destroy(
        Entity $entity,
        EntityKnowledgeGroup $entityKnowledgeGroup,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureBelongsToEntity($entity, $entityKnowledgeGroup);

        if ($entityKnowledgeGroup->is_default) {
            throw ValidationException::withMessages([
                'group' => 'The default knowledge group cannot be deleted.',
            ]);
        }

        $itemCount = $entityKnowledgeGroup->items()->withoutGlobalScopes()->count();
        if ($itemCount > 0) {
            throw ValidationException::withMessages([
                'group' => 'Remove or reassign knowledge items before deleting this group.',
            ]);
        }

        $entityKnowledgeGroup->delete();

        $auditLogger->log('entity_knowledge_group.deleted', $entityKnowledgeGroup, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Knowledge group deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(
        Request $request,
        Entity $entity,
        ?EntityKnowledgeGroup $entityKnowledgeGroup = null,
    ): array {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('entity_knowledge_groups', 'name')
                    ->where(fn ($query) => $query->where('entity_id', $entity->id))
                    ->ignore($entityKnowledgeGroup?->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('entity_knowledge_groups', 'code')
                    ->where(fn ($query) => $query->where('entity_id', $entity->id))
                    ->ignore($entityKnowledgeGroup?->id),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function assertCanDeactivate(Entity $entity, EntityKnowledgeGroup $group): void
    {
        $otherActive = EntityKnowledgeGroup::query()
            ->withoutGlobalScopes()
            ->where('entity_id', $entity->id)
            ->whereKeyNot($group->id)
            ->where('is_active', true)
            ->exists();

        if (! $otherActive) {
            throw ValidationException::withMessages([
                'is_active' => 'At least one active knowledge group is required.',
            ]);
        }
    }

    private function ensureBelongsToEntity(Entity $entity, EntityKnowledgeGroup $group): void
    {
        if ((int) $group->entity_id !== (int) $entity->id) {
            abort(404);
        }
    }
}
