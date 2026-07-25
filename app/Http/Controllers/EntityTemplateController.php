<?php

namespace App\Http\Controllers;

use App\Enums\TemplateChannel;
use App\Models\Entity;
use App\Models\EntityTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntityTemplateController extends Controller
{
    public function store(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $entity);
        $data['entity_id'] = $entity->id;

        $template = EntityTemplate::query()->create($data);

        $auditLogger->log('entity_template.created', $template, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Template created.');
    }

    public function update(
        Request $request,
        Entity $entity,
        EntityTemplate $entityTemplate,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureBelongsToEntity($entity, $entityTemplate);

        $entityTemplate->update($this->validated($request, $entity, $entityTemplate));

        $auditLogger->log('entity_template.updated', $entityTemplate, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(
        Entity $entity,
        EntityTemplate $entityTemplate,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureBelongsToEntity($entity, $entityTemplate);

        $entityTemplate->delete();

        $auditLogger->log('entity_template.deleted', $entityTemplate, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Entity $entity, ?EntityTemplate $entityTemplate = null): array
    {
        $channelValues = array_column(TemplateChannel::cases(), 'value');

        $data = $request->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['required', 'string', 'distinct', Rule::in($channelValues)],
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('entity_templates', 'slug')
                    ->where(fn ($query) => $query->where('entity_id', $entity->id))
                    ->ignore($entityTemplate?->id),
            ],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['types'] = array_values(array_unique($data['types']));
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function ensureBelongsToEntity(Entity $entity, EntityTemplate $entityTemplate): void
    {
        if ((int) $entityTemplate->entity_id !== (int) $entity->id) {
            abort(404);
        }
    }
}
