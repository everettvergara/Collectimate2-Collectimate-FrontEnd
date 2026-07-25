<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EntityStatus;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EntityStatusController extends Controller
{
    public function store(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $entity);
        $data['entity_id'] = $entity->id;

        $status = EntityStatus::query()->create($data);

        $auditLogger->log('entity_status.created', $status, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Entity status created.');
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
        $sourceStatuses = EntityStatus::query()
            ->where('entity_id', $sourceEntityId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $existingNames = EntityStatus::query()
            ->where('entity_id', $entity->id)
            ->pluck('name')
            ->all();
        $existingLookup = array_fill_keys($existingNames, true);

        $copied = 0;
        $skipped = 0;

        DB::transaction(function () use ($entity, $sourceStatuses, $existingLookup, &$copied, &$skipped) {
            foreach ($sourceStatuses as $source) {
                if (isset($existingLookup[$source->name])) {
                    $skipped++;

                    continue;
                }

                EntityStatus::query()->create([
                    'entity_id' => $entity->id,
                    'name' => $source->name,
                    'code' => $source->code,
                    'color' => $source->color,
                    'text_color' => $source->text_color ?: '#ffffff',
                    'sort_order' => $source->sort_order,
                    'is_active' => $source->is_active,
                ]);
                $copied++;
            }
        });

        $auditLogger->log('entity_status.copied', $entity, null, [
            'entity_id' => $entity->id,
            'source_entity_id' => $sourceEntityId,
            'copied_count' => $copied,
            'skipped_count' => $skipped,
        ]);

        return back()->with(
            'success',
            "Copied {$copied} statuses ({$skipped} skipped as duplicates).",
        );
    }

    public function update(Request $request, Entity $entity, EntityStatus $entityStatus, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureBelongsToEntity($entity, $entityStatus);

        $entityStatus->update($this->validated($request, $entity, $entityStatus));

        $auditLogger->log('entity_status.updated', $entityStatus, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Entity status updated.');
    }

    public function destroy(Entity $entity, EntityStatus $entityStatus, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureBelongsToEntity($entity, $entityStatus);

        $entityStatus->delete();

        $auditLogger->log('entity_status.deleted', $entityStatus, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Entity status deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Entity $entity, ?EntityStatus $entityStatus = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('entity_statuses', 'name')
                    ->where(fn ($query) => $query->where('entity_id', $entity->id))
                    ->ignore($entityStatus?->id),
            ],
            'code' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['text_color'] = filled($data['text_color'] ?? null)
            ? (string) $data['text_color']
            : '#ffffff';
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($entityStatus === null && $data['sort_order'] === 0) {
            $data['sort_order'] = (int) EntityStatus::query()
                ->where('entity_id', $entity->id)
                ->max('sort_order') + 10;
        }

        return $data;
    }

    private function ensureBelongsToEntity(Entity $entity, EntityStatus $entityStatus): void
    {
        if ((int) $entityStatus->entity_id !== (int) $entity->id) {
            abort(404);
        }
    }
}
