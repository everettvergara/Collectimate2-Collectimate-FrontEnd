<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeItemType;
use App\Models\Entity;
use App\Models\EntityKnowledgeGroup;
use App\Models\EntityKnowledgeItem;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityKnowledgeItemController extends Controller
{
    public function store(Request $request, Entity $entity, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $entity);
        $data['entity_id'] = $entity->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        if (($data['type'] ?? null) === KnowledgeItemType::Pdf->value) {
            $data = array_merge($data, $this->storePdf($request, $entity));
        }

        $item = EntityKnowledgeItem::query()->create($data);

        $auditLogger->log('entity_knowledge_item.created', $item, null, [
            'entity_id' => $entity->id,
            'type' => $item->type?->value ?? $item->getAttributes()['type'] ?? null,
        ]);

        return back()->with('success', 'Knowledge item created.');
    }

    public function update(
        Request $request,
        Entity $entity,
        EntityKnowledgeItem $entityKnowledgeItem,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureBelongsToEntity($entity, $entityKnowledgeItem);

        $data = $this->validated($request, $entity, $entityKnowledgeItem);
        $data['updated_by'] = $request->user()->id;

        $type = KnowledgeItemType::from($data['type']);

        if ($type === KnowledgeItemType::Pdf) {
            if ($request->hasFile('file')) {
                $this->deleteStoredFile($entityKnowledgeItem);
                $data = array_merge($data, $this->storePdf($request, $entity));
            } elseif (! $entityKnowledgeItem->file_path) {
                return back()->withErrors([
                    'file' => 'A PDF file is required.',
                ]);
            } else {
                unset($data['body'], $data['url']);
            }
        } else {
            $this->deleteStoredFile($entityKnowledgeItem);
            $data['file_path'] = null;
            $data['file_disk'] = 'local';
            $data['original_name'] = null;
            $data['mime'] = null;
            $data['size'] = 0;

            if ($type === KnowledgeItemType::Text) {
                $data['url'] = null;
            } else {
                $data['body'] = null;
            }
        }

        $entityKnowledgeItem->update($data);

        $auditLogger->log('entity_knowledge_item.updated', $entityKnowledgeItem, null, [
            'entity_id' => $entity->id,
            'type' => $type->value,
        ]);

        return back()->with('success', 'Knowledge item updated.');
    }

    public function destroy(
        Entity $entity,
        EntityKnowledgeItem $entityKnowledgeItem,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureBelongsToEntity($entity, $entityKnowledgeItem);

        $entityKnowledgeItem->delete();

        $auditLogger->log('entity_knowledge_item.deleted', $entityKnowledgeItem, null, [
            'entity_id' => $entity->id,
        ]);

        return back()->with('success', 'Knowledge item deleted.');
    }

    public function download(
        Entity $entity,
        EntityKnowledgeItem $entityKnowledgeItem,
    ): StreamedResponse {
        $this->ensureBelongsToEntity($entity, $entityKnowledgeItem);

        if ($entityKnowledgeItem->type !== KnowledgeItemType::Pdf || ! $entityKnowledgeItem->file_path) {
            abort(404);
        }

        $disk = $entityKnowledgeItem->file_disk ?: 'local';

        if (! Storage::disk($disk)->exists($entityKnowledgeItem->file_path)) {
            abort(404);
        }

        return Storage::disk($disk)->download(
            $entityKnowledgeItem->file_path,
            $entityKnowledgeItem->original_name ?: 'knowledge.pdf',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(
        Request $request,
        Entity $entity,
        ?EntityKnowledgeItem $entityKnowledgeItem = null,
    ): array {
        $typeValues = array_column(KnowledgeItemType::cases(), 'value');
        $type = (string) $request->input('type');

        $rules = [
            'entity_knowledge_group_id' => [
                'required',
                'integer',
                Rule::exists('entity_knowledge_groups', 'id')->where(
                    fn ($query) => $query->where('entity_id', $entity->id),
                ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($typeValues)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048', 'url'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];

        if ($type === KnowledgeItemType::Text->value) {
            $rules['body'] = ['required', 'string'];
        } elseif ($type === KnowledgeItemType::Url->value) {
            $rules['url'] = ['required', 'string', 'max:2048', 'url'];
        } elseif ($type === KnowledgeItemType::Pdf->value) {
            $rules['file'] = $entityKnowledgeItem?->file_path
                ? ['nullable', 'file', 'mimes:pdf', 'max:10240']
                : ['required', 'file', 'mimes:pdf', 'max:10240'];
        }

        $data = $request->validate($rules);

        $group = EntityKnowledgeGroup::query()
            ->withoutGlobalScopes()
            ->whereKey($data['entity_knowledge_group_id'])
            ->where('entity_id', $entity->id)
            ->firstOrFail();

        $data['entity_knowledge_group_id'] = $group->id;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        unset($data['file']);

        if ($type === KnowledgeItemType::Text->value) {
            $data['url'] = null;
            $data['body'] = $data['body'] ?? null;
        } elseif ($type === KnowledgeItemType::Url->value) {
            $data['body'] = null;
        } else {
            $data['body'] = null;
            $data['url'] = null;
        }

        return $data;
    }

    /**
     * @return array{file_path: string, file_disk: string, original_name: string, mime: ?string, size: int}
     */
    private function storePdf(Request $request, Entity $entity): array
    {
        $file = $request->file('file');
        $path = $file->store('entity-knowledge/'.$entity->id, 'local');

        return [
            'file_path' => $path,
            'file_disk' => 'local',
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    private function deleteStoredFile(EntityKnowledgeItem $item): void
    {
        if (! $item->file_path) {
            return;
        }

        $disk = $item->file_disk ?: 'local';
        Storage::disk($disk)->delete($item->file_path);
    }

    private function ensureBelongsToEntity(Entity $entity, EntityKnowledgeItem $item): void
    {
        if ((int) $item->entity_id !== (int) $entity->id) {
            abort(404);
        }
    }
}
