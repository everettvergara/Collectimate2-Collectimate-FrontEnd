<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import InputError from '@/Components/InputError.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    entity: Object,
    group: Object,
    summary: Object,
    knowledgeItemTypes: Array,
    can: Object,
});

const showKnowledgeItemModal = ref(false);
const knowledgeFileInputRef = ref(null);
const editingKnowledgeItemId = ref(null);
const editingKnowledgeItemHasFile = ref(false);

const knowledgeItemColumns = [
    { id: 'title', accessorKey: 'title', header: 'Title' },
    { id: 'type', accessorKey: 'type', header: 'Type' },
    { id: 'preview', header: 'Preview' },
    { id: 'active', header: 'Active' },
    { id: 'actions', header: 'Actions' },
];

const knowledgeItemRows = computed(() => props.group?.items ?? []);

const knowledgeTypeOptions = computed(() =>
    (props.knowledgeItemTypes ?? ['text', 'url', 'pdf']).map((value) => ({
        value,
        label: value === 'pdf' ? 'PDF' : value === 'url' ? 'URL' : 'Text',
    })),
);

const knowledgeItemForm = useForm({
    entity_knowledge_group_id: props.group?.id ?? null,
    title: '',
    type: 'text',
    body: '',
    url: '',
    file: null,
    sort_order: 0,
    is_active: true,
    notes: '',
});

function resetKnowledgeItemForm() {
    editingKnowledgeItemId.value = null;
    editingKnowledgeItemHasFile.value = false;
    knowledgeItemForm.reset();
    knowledgeItemForm.clearErrors();
    knowledgeItemForm.entity_knowledge_group_id = props.group.id;
    knowledgeItemForm.type = 'text';
    knowledgeItemForm.body = '';
    knowledgeItemForm.url = '';
    knowledgeItemForm.file = null;
    knowledgeItemForm.sort_order = 0;
    knowledgeItemForm.is_active = true;
    knowledgeItemForm.notes = '';
    if (knowledgeFileInputRef.value) {
        knowledgeFileInputRef.value.value = '';
    }
}

function openKnowledgeItemModal() {
    resetKnowledgeItemForm();
    showKnowledgeItemModal.value = true;
}

function closeKnowledgeItemModal() {
    showKnowledgeItemModal.value = false;
    resetKnowledgeItemForm();
}

function startEditKnowledgeItem(item) {
    editingKnowledgeItemId.value = item.id;
    editingKnowledgeItemHasFile.value = item.type === 'pdf' && !!item.file_path;
    knowledgeItemForm.entity_knowledge_group_id = props.group.id;
    knowledgeItemForm.title = item.title ?? '';
    knowledgeItemForm.type = item.type ?? 'text';
    knowledgeItemForm.body = item.body ?? '';
    knowledgeItemForm.url = item.url ?? '';
    knowledgeItemForm.file = null;
    knowledgeItemForm.sort_order = item.sort_order ?? 0;
    knowledgeItemForm.is_active = !!item.is_active;
    knowledgeItemForm.notes = item.notes ?? '';
    knowledgeItemForm.clearErrors();
    if (knowledgeFileInputRef.value) {
        knowledgeFileInputRef.value.value = '';
    }
    showKnowledgeItemModal.value = true;
}

function onKnowledgeFileChange(event) {
    knowledgeItemForm.file = event.target.files?.[0] ?? null;
}

function submitKnowledgeItem() {
    knowledgeItemForm.entity_knowledge_group_id = props.group.id;

    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeKnowledgeItemModal(),
    };

    if (editingKnowledgeItemId.value) {
        knowledgeItemForm.put(
            route('entities.knowledge.update', [props.entity.id, editingKnowledgeItemId.value]),
            options,
        );
        return;
    }

    knowledgeItemForm.post(route('entities.knowledge.store', props.entity.id), options);
}

function destroyKnowledgeItem(itemId) {
    if (confirm('Delete this knowledge item?')) {
        router.delete(route('entities.knowledge.destroy', [props.entity.id, itemId]));
    }
}

function knowledgeTypeLabel(value) {
    if (value === 'pdf') return 'PDF';
    if (value === 'url') return 'URL';
    if (value === 'text') return 'Text';
    return value || '—';
}

function truncateText(value, max = 80) {
    const text = String(value ?? '').replace(/\s+/g, ' ').trim();
    if (!text) return '—';
    return text.length > max ? `${text.slice(0, max)}…` : text;
}

const backHref = computed(() =>
    route('entities.show', { entity: props.entity.id, tab: 'knowledge-groups' }),
);
</script>

<template>
    <Head :title="`${group.name} · Knowledge`" />
    <AppLayout>
        <template #header>{{ group.name }}</template>

        <div class="space-y-4 max-w-7xl">
            <div
                class="p-4 border rounded"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <Link
                            :href="backHref"
                            class="text-sm underline"
                            style="color: var(--color-primary)"
                        >
                            ← {{ entity.name }} · Knowledge Groups
                        </Link>
                        <div class="page-title flex flex-wrap items-center gap-2">
                            <span>{{ group.name }}</span>
                            <span
                                v-if="group.is_default"
                                class="inline-flex px-2 py-0.5 rounded text-xs border"
                                style="
                                    color: var(--color-text-muted);
                                    border-color: var(--color-border);
                                    background: var(--color-bg-subtle);
                                "
                            >
                                Default
                            </span>
                        </div>
                        <div class="text-sm" style="color: var(--color-text-muted)">
                            {{ entity.entity_code }}
                            <span v-if="group.code"> · {{ group.code }}</span>
                        </div>
                        <p
                            v-if="group.description"
                            class="text-sm max-w-2xl"
                            style="color: var(--color-text-muted)"
                        >
                            {{ group.description }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 shrink-0">
                        <div
                            class="px-3 py-2 border rounded"
                            style="background: var(--color-bg-app); border-color: var(--color-border)"
                        >
                            <div class="form-label mb-0.5">Items</div>
                            <div class="page-title tabular-nums">{{ summary?.total ?? 0 }}</div>
                        </div>
                        <div
                            class="px-3 py-2 border rounded"
                            style="background: var(--color-bg-app); border-color: var(--color-border)"
                        >
                            <div class="form-label mb-0.5">Active</div>
                            <div class="page-title tabular-nums">{{ summary?.active ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="p-4 border rounded space-y-3"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="form-label mb-0">Knowledge Center</div>
                    <Button v-if="can.update" size="sm" @click="openKnowledgeItemModal">Add</Button>
                </div>

                <CollectimateDataTable
                    :value="knowledgeItemRows"
                    :columns="knowledgeItemColumns"
                    :rows="Math.max(knowledgeItemRows.length, 1)"
                    :total-records="knowledgeItemRows.length"
                    :first="0"
                    :paginator="true"
                >
                    <template #cell.type="{ row }">
                        {{ knowledgeTypeLabel(row.type) }}
                    </template>
                    <template #cell.preview="{ row }">
                        <span
                            v-if="row.type === 'text'"
                            class="block max-w-xs truncate"
                            :title="row.body"
                        >
                            {{ truncateText(row.body) }}
                        </span>
                        <a
                            v-else-if="row.type === 'url' && row.url"
                            :href="row.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm underline truncate block max-w-xs"
                            style="color: var(--color-primary)"
                        >
                            {{ truncateText(row.url, 60) }}
                        </a>
                        <a
                            v-else-if="row.type === 'pdf'"
                            :href="route('entities.knowledge.download', [entity.id, row.id])"
                            class="text-sm underline"
                            style="color: var(--color-primary)"
                        >
                            {{ row.original_name || 'Download PDF' }}
                        </a>
                        <span v-else>—</span>
                    </template>
                    <template #cell.active="{ row }">
                        {{ row.is_active ? 'Yes' : 'No' }}
                    </template>
                    <template #cell.actions="{ row }">
                        <ListingRowActions
                            v-if="can.update"
                            :on-edit="() => startEditKnowledgeItem(row)"
                            :on-delete="() => destroyKnowledgeItem(row.id)"
                        />
                    </template>
                </CollectimateDataTable>
            </div>
        </div>

        <Modal :show="showKnowledgeItemModal" max-width="lg" @close="closeKnowledgeItemModal">
            <form class="p-6 space-y-4" @submit.prevent="submitKnowledgeItem">
                <h2 class="text-lg font-semibold">
                    {{ editingKnowledgeItemId ? 'Edit knowledge item' : 'Add knowledge item' }}
                </h2>
                <div>
                    <label class="form-label block mb-1">Group</label>
                    <div class="text-sm py-1">{{ group.name }}</div>
                </div>
                <div>
                    <label class="form-label block mb-1">Title</label>
                    <Input v-model="knowledgeItemForm.title" class="w-full" />
                    <InputError :message="knowledgeItemForm.errors.title" />
                </div>
                <div>
                    <label class="form-label block mb-1">Type</label>
                    <Select
                        v-model="knowledgeItemForm.type"
                        :options="knowledgeTypeOptions"
                        class="w-full"
                    />
                    <InputError :message="knowledgeItemForm.errors.type" />
                </div>
                <div v-if="knowledgeItemForm.type === 'text'">
                    <label class="form-label block mb-1">Text</label>
                    <Textarea v-model="knowledgeItemForm.body" rows="8" class="w-full" />
                    <InputError :message="knowledgeItemForm.errors.body" />
                </div>
                <div v-else-if="knowledgeItemForm.type === 'url'">
                    <label class="form-label block mb-1">URL</label>
                    <Input v-model="knowledgeItemForm.url" class="w-full" />
                    <InputError :message="knowledgeItemForm.errors.url" />
                </div>
                <div v-else-if="knowledgeItemForm.type === 'pdf'">
                    <label class="form-label block mb-1">PDF</label>
                    <input
                        ref="knowledgeFileInputRef"
                        type="file"
                        accept="application/pdf,.pdf"
                        class="block w-full text-sm"
                        @change="onKnowledgeFileChange"
                    />
                    <p
                        v-if="editingKnowledgeItemHasFile && !knowledgeItemForm.file"
                        class="mt-1 text-sm"
                        style="color: var(--color-text-muted)"
                    >
                        Current file kept unless you choose a replacement.
                    </p>
                    <InputError :message="knowledgeItemForm.errors.file" />
                </div>
                <div>
                    <label class="form-label block mb-1">Notes</label>
                    <Textarea v-model="knowledgeItemForm.notes" rows="2" class="w-full" />
                </div>
                <div>
                    <label class="form-label block mb-1">Order</label>
                    <Input
                        v-model.number="knowledgeItemForm.sort_order"
                        type="number"
                        min="0"
                        class="w-full"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="knowledge-item-active" v-model="knowledgeItemForm.is_active" />
                    <label for="knowledge-item-active" class="text-sm">Active</label>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeKnowledgeItemModal">
                        Cancel
                    </Button>
                    <Button type="submit" size="sm" :disabled="knowledgeItemForm.processing">
                        Save
                    </Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
