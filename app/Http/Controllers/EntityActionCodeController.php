<?php

namespace App\Http\Controllers;

use App\Enums\ActionCodeClassification;
use App\Models\Entity;
use App\Models\EntityActionCode;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EntityActionCodeController extends Controller
{
    public function store(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $entity);
        $data['entity_id'] = $entity->id;

        $actionCode = EntityActionCode::query()->create($data);

        $auditLogger->log('entity_action_code.created', $actionCode, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Entity action code created.');
    }

    public function copy(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'source_entity_id' => [
                'required',
                'integer',
                Rule::notIn([$entity->id]),
                Rule::exists('entities', 'id'),
            ],
        ]);

        $sourceEntityId = (int) $data['source_entity_id'];
        $sourceActionCodes = EntityActionCode::query()
            ->where('entity_id', $sourceEntityId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $existingNames = EntityActionCode::query()
            ->where('entity_id', $entity->id)
            ->pluck('name')
            ->all();
        $existingLookup = array_fill_keys($existingNames, true);

        $copied = 0;
        $skipped = 0;

        DB::transaction(function () use ($entity, $sourceActionCodes, $existingLookup, &$copied, &$skipped) {
            foreach ($sourceActionCodes as $source) {
                if (isset($existingLookup[$source->name])) {
                    $skipped++;

                    continue;
                }

                EntityActionCode::query()->create([
                    'entity_id' => $entity->id,
                    'name' => $source->name,
                    'code' => $source->code,
                    'classification' => $source->classification ?? ActionCodeClassification::Neutral,
                    'sort_order' => $source->sort_order,
                    'is_active' => $source->is_active,
                ]);
                $copied++;
            }
        });

        $auditLogger->log('entity_action_code.copied', $entity, null, [
            'entity_id' => $entity->id,
            'source_entity_id' => $sourceEntityId,
            'copied_count' => $copied,
            'skipped_count' => $skipped,
        ]);

        return back()->with(
            'success',
            "Copied {$copied} action codes ({$skipped} skipped as duplicates).",
        );
    }

    public function update(Request $request, Entity $entity, EntityActionCode $entityActionCode, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureBelongsToEntity($entity, $entityActionCode);

        $entityActionCode->update($this->validated($request, $entity, $entityActionCode));

        $auditLogger->log('entity_action_code.updated', $entityActionCode, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Entity action code updated.');
    }

    public function destroy(Entity $entity, EntityActionCode $entityActionCode, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureBelongsToEntity($entity, $entityActionCode);

        $entityActionCode->delete();

        $auditLogger->log('entity_action_code.deleted', $entityActionCode, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Entity action code deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Entity $entity, ?EntityActionCode $entityActionCode = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('entity_action_codes', 'name')
                    ->where(fn ($query) => $query->where('entity_id', $entity->id))
                    ->ignore($entityActionCode?->id),
            ],
            'code' => ['nullable', 'string', 'max:100'],
            'classification' => ['required', Rule::enum(ActionCodeClassification::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($entityActionCode === null && $data['sort_order'] === 0) {
            $data['sort_order'] = (int) EntityActionCode::query()
                ->where('entity_id', $entity->id)
                ->max('sort_order') + 10;
        }

        return $data;
    }

    private function ensureBelongsToEntity(Entity $entity, EntityActionCode $entityActionCode): void
    {
        if ((int) $entityActionCode->entity_id !== (int) $entity->id) {
            abort(404);
        }
    }
}
