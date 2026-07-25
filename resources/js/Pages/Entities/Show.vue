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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    entity: Object,
    campaignStatuses: Array,
    actionCodeClassifications: Array,
    templateChannels: Array,
    copySources: Array,
    can: Object,
});

const showDeleteModal = ref(false);
const showCampaignModal = ref(false);
const showStatusModal = ref(false);
const showStatusCopyModal = ref(false);
const showActionModal = ref(false);
const showActionCopyModal = ref(false);
const showTemplateModal = ref(false);

const deleteForm = useForm({ confirmation_name: '' });
const nameMatches = computed(
    () => deleteForm.confirmation_name.trim() === (props.entity?.name ?? ''),
);

const copySourceOptions = computed(() =>
    (props.copySources ?? []).map((item) => ({
        value: item.id,
        label: item.entity_code ? `${item.name} (${item.entity_code})` : item.name,
    })),
);

const statusColumns = [
    { id: 'name', accessorKey: 'name', header: 'Name' },
    { id: 'code', accessorKey: 'code', header: 'Code' },
    { id: 'color', header: 'Color' },
    { id: 'text_color', header: 'Text color' },
    { id: 'sort_order', accessorKey: 'sort_order', header: 'Order' },
    { id: 'active', header: 'Active' },
    { id: 'actions', header: 'Actions' },
];

const actionColumns = [
    { id: 'name', accessorKey: 'name', header: 'Name' },
    { id: 'code', accessorKey: 'code', header: 'Code' },
    { id: 'classification', header: 'Classification' },
    { id: 'sort_order', accessorKey: 'sort_order', header: 'Order' },
    { id: 'active', header: 'Active' },
    { id: 'actions', header: 'Actions' },
];

const templateColumns = [
    { id: 'types', header: 'Type(s)' },
    { id: 'slug', accessorKey: 'slug', header: 'Slug' },
    { id: 'body', header: 'Template' },
    { id: 'active', header: 'Active' },
    { id: 'actions', header: 'Actions' },
];

const templateChannelOptions = computed(() =>
    (props.templateChannels ?? ['sms', 'email', 'chat']).map((value) => ({
        value,
        label: value === 'sms' ? 'SMS' : value.charAt(0).toUpperCase() + value.slice(1),
    })),
);

const classificationOptions = computed(() =>
    (props.actionCodeClassifications ?? ['positive', 'negative', 'neutral']).map((value) => ({
        value,
        label: value.charAt(0).toUpperCase() + value.slice(1),
    })),
);

function classificationLabel(value) {
    if (!value) return '—';
    return String(value).charAt(0).toUpperCase() + String(value).slice(1);
}

function classificationChipStyle(value) {
    if (value === 'positive') {
        return { background: '#166534', color: '#fff', borderColor: '#166534' };
    }
    if (value === 'negative') {
        return { background: '#991b1b', color: '#fff', borderColor: '#991b1b' };
    }
    return {
        background: 'var(--color-bg-surface)',
        color: 'var(--color-text-muted)',
        borderColor: 'var(--color-border)',
    };
}

const statusRows = computed(() => props.entity?.entity_statuses ?? []);
const actionRows = computed(() => props.entity?.entity_action_codes ?? []);
const templateRows = computed(() => props.entity?.entity_templates ?? []);

function channelLabel(value) {
    if (value === 'sms') return 'SMS';
    if (!value) return '—';
    return String(value).charAt(0).toUpperCase() + String(value).slice(1);
}

function formatTemplateTypes(types) {
    if (!Array.isArray(types) || !types.length) return '—';
    return types.map(channelLabel).join(', ');
}

function truncateTemplateBody(body, max = 80) {
    const text = String(body ?? '').replace(/\s+/g, ' ').trim();
    if (!text) return '—';
    return text.length > max ? `${text.slice(0, max)}…` : text;
}

function openDeleteModal() {
    deleteForm.reset();
    deleteForm.clearErrors();
    showDeleteModal.value = true;
}

function confirmDelete() {
    if (!nameMatches.value) return;
    deleteForm.delete(route('entities.destroy', props.entity.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
        },
    });
}

function destroyCampaign(campaignId) {
    if (confirm('Delete this campaign and all of its accounts? This cannot be undone.')) {
        router.delete(route('campaigns.destroy', campaignId));
    }
}

const campaignForm = useForm({
    entity_id: props.entity?.id ?? null,
    campaign_code: '',
    name: '',
    description: '',
    status: 'active',
});

function openCampaignModal() {
    campaignForm.reset();
    campaignForm.clearErrors();
    campaignForm.entity_id = props.entity.id;
    campaignForm.status = 'active';
    showCampaignModal.value = true;
}

function closeCampaignModal() {
    showCampaignModal.value = false;
    campaignForm.reset();
    campaignForm.clearErrors();
    campaignForm.entity_id = props.entity.id;
    campaignForm.status = 'active';
}

function submitCampaign() {
    campaignForm.entity_id = props.entity.id;
    campaignForm.post(route('campaigns.store'), {
        preserveScroll: true,
        onSuccess: () => closeCampaignModal(),
    });
}

const statusForm = useForm({
    name: '',
    code: '',
    color: '',
    text_color: '#ffffff',
    sort_order: 0,
    is_active: true,
});
const editingStatusId = ref(null);
const statusCopyForm = useForm({ source_entity_id: null });

function resetStatusForm() {
    editingStatusId.value = null;
    statusForm.reset();
    statusForm.clearErrors();
    statusForm.color = '';
    statusForm.text_color = '#ffffff';
    statusForm.is_active = true;
    statusForm.sort_order = 0;
}

function openStatusModal() {
    resetStatusForm();
    showStatusModal.value = true;
}

function closeStatusModal() {
    showStatusModal.value = false;
    resetStatusForm();
}

function startEditStatus(status) {
    editingStatusId.value = status.id;
    statusForm.name = status.name;
    statusForm.code = status.code ?? '';
    statusForm.color = status.color ?? '';
    statusForm.text_color = status.text_color || '#ffffff';
    statusForm.sort_order = status.sort_order ?? 0;
    statusForm.is_active = !!status.is_active;
    statusForm.clearErrors();
    showStatusModal.value = true;
}

function submitStatus() {
    if (editingStatusId.value) {
        statusForm.put(route('entities.statuses.update', [props.entity.id, editingStatusId.value]), {
            preserveScroll: true,
            onSuccess: () => closeStatusModal(),
        });
    } else {
        statusForm.post(route('entities.statuses.store', props.entity.id), {
            preserveScroll: true,
            onSuccess: () => closeStatusModal(),
        });
    }
}

function destroyStatus(statusId) {
    if (confirm('Delete this entity status?')) {
        router.delete(route('entities.statuses.destroy', [props.entity.id, statusId]));
    }
}

function openStatusCopyModal() {
    statusCopyForm.reset();
    statusCopyForm.clearErrors();
    statusCopyForm.source_entity_id = null;
    showStatusCopyModal.value = true;
}

function closeStatusCopyModal() {
    showStatusCopyModal.value = false;
    statusCopyForm.reset();
    statusCopyForm.clearErrors();
    statusCopyForm.source_entity_id = null;
}

function submitStatusCopy() {
    const source = (props.copySources ?? []).find(
        (item) => item.id === statusCopyForm.source_entity_id,
    );
    const label = source?.name ?? 'this entity';
    if (
        !statusCopyForm.source_entity_id ||
        !confirm(`Copy all statuses from ${label}? Existing names will be skipped.`)
    ) {
        return;
    }

    statusCopyForm.post(route('entities.statuses.copy', props.entity.id), {
        preserveScroll: true,
        onSuccess: () => closeStatusCopyModal(),
    });
}

const actionForm = useForm({
    name: '',
    code: '',
    classification: 'neutral',
    sort_order: 0,
    is_active: true,
});
const editingActionId = ref(null);
const actionCopyForm = useForm({ source_entity_id: null });

function resetActionForm() {
    editingActionId.value = null;
    actionForm.reset();
    actionForm.clearErrors();
    actionForm.classification = 'neutral';
    actionForm.is_active = true;
    actionForm.sort_order = 0;
}

function openActionModal() {
    resetActionForm();
    showActionModal.value = true;
}

function closeActionModal() {
    showActionModal.value = false;
    resetActionForm();
}

function startEditAction(action) {
    editingActionId.value = action.id;
    actionForm.name = action.name;
    actionForm.code = action.code ?? '';
    actionForm.classification = action.classification ?? 'neutral';
    actionForm.sort_order = action.sort_order ?? 0;
    actionForm.is_active = !!action.is_active;
    actionForm.clearErrors();
    showActionModal.value = true;
}

function submitAction() {
    if (editingActionId.value) {
        actionForm.put(route('entities.action-codes.update', [props.entity.id, editingActionId.value]), {
            preserveScroll: true,
            onSuccess: () => closeActionModal(),
        });
    } else {
        actionForm.post(route('entities.action-codes.store', props.entity.id), {
            preserveScroll: true,
            onSuccess: () => closeActionModal(),
        });
    }
}

function destroyAction(actionId) {
    if (confirm('Delete this entity action code?')) {
        router.delete(route('entities.action-codes.destroy', [props.entity.id, actionId]));
    }
}

function openActionCopyModal() {
    actionCopyForm.reset();
    actionCopyForm.clearErrors();
    actionCopyForm.source_entity_id = null;
    showActionCopyModal.value = true;
}

function closeActionCopyModal() {
    showActionCopyModal.value = false;
    actionCopyForm.reset();
    actionCopyForm.clearErrors();
    actionCopyForm.source_entity_id = null;
}

function submitActionCopy() {
    const source = (props.copySources ?? []).find(
        (item) => item.id === actionCopyForm.source_entity_id,
    );
    const label = source?.name ?? 'this entity';
    if (
        !actionCopyForm.source_entity_id ||
        !confirm(`Copy all action codes from ${label}? Existing names will be skipped.`)
    ) {
        return;
    }

    actionCopyForm.post(route('entities.action-codes.copy', props.entity.id), {
        preserveScroll: true,
        onSuccess: () => closeActionCopyModal(),
    });
}

const templateForm = useForm({
    types: [],
    slug: '',
    body: '',
    is_active: true,
});
const editingTemplateId = ref(null);

function isTemplateChannelSelected(channel) {
    return (templateForm.types ?? []).includes(channel);
}

function toggleTemplateChannel(channel, checked) {
    const current = [...(templateForm.types ?? [])];
    const index = current.indexOf(channel);
    if (checked && index === -1) {
        current.push(channel);
    } else if (!checked && index !== -1) {
        current.splice(index, 1);
    }
    templateForm.types = current;
}

function resetTemplateForm() {
    editingTemplateId.value = null;
    templateForm.reset();
    templateForm.clearErrors();
    templateForm.types = [];
    templateForm.slug = '';
    templateForm.body = '';
    templateForm.is_active = true;
}

function openTemplateModal() {
    resetTemplateForm();
    showTemplateModal.value = true;
}

function closeTemplateModal() {
    showTemplateModal.value = false;
    resetTemplateForm();
}

function startEditTemplate(template) {
    editingTemplateId.value = template.id;
    templateForm.types = Array.isArray(template.types) ? [...template.types] : [];
    templateForm.slug = template.slug ?? '';
    templateForm.body = template.body ?? '';
    templateForm.is_active = !!template.is_active;
    templateForm.clearErrors();
    showTemplateModal.value = true;
}

function submitTemplate() {
    if (editingTemplateId.value) {
        templateForm.put(
            route('entities.templates.update', [props.entity.id, editingTemplateId.value]),
            {
                preserveScroll: true,
                onSuccess: () => closeTemplateModal(),
            },
        );
    } else {
        templateForm.post(route('entities.templates.store', props.entity.id), {
            preserveScroll: true,
            onSuccess: () => closeTemplateModal(),
        });
    }
}

function destroyTemplate(templateId) {
    if (confirm('Delete this template?')) {
        router.delete(route('entities.templates.destroy', [props.entity.id, templateId]));
    }
}
</script>

<template>
    <Head :title="entity.name" />
    <AppLayout>
        <template #header>{{ entity.name }}</template>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start max-w-7xl">
            <div class="space-y-6 min-w-0">
                <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="flex items-start gap-4">
                        <div
                            v-if="entity.logo_url"
                            class="h-16 w-16 rounded-md overflow-hidden shrink-0 border flex items-center justify-center"
                            style="background: var(--color-bg); border-color: var(--color-border)"
                        >
                            <img
                                :src="entity.logo_url"
                                :alt="`${entity.name} logo`"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3 flex-1 min-w-0">
                            <div>
                                <div class="form-label">Code</div>
                                <div>{{ entity.entity_code }}</div>
                            </div>
                            <div>
                                <div class="form-label">Name</div>
                                <div>{{ entity.name }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <Link v-if="can.update" :href="route('entities.edit', entity.id)">
                            <Button size="sm">Edit</Button>
                        </Link>
                        <Button v-if="can.delete" variant="destructive" size="sm" @click="openDeleteModal">
                            Delete entity
                        </Button>
                    </div>
                </div>

                <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="form-label mb-0">Entity Statuses</div>
                        <div v-if="can.update" class="flex gap-2">
                            <Button size="sm" variant="secondary" @click="openStatusCopyModal">Copy</Button>
                            <Button size="sm" @click="openStatusModal">Add</Button>
                        </div>
                    </div>

                    <CollectimateDataTable
                        :value="statusRows"
                        :columns="statusColumns"
                        :rows="Math.max(statusRows.length, 1)"
                        :total-records="statusRows.length"
                        :first="0"
                        :paginator="true"
                    >
                        <template #cell.code="{ row }">{{ row.code || '—' }}</template>
                        <template #cell.color="{ row }">
                            <div v-if="row.color" class="inline-flex items-center gap-1.5 min-w-0">
                                <span
                                    class="h-3.5 w-3.5 rounded-sm shrink-0 border"
                                    :style="{ background: row.color, borderColor: 'var(--color-border)' }"
                                />
                                <span class="truncate">{{ row.color }}</span>
                            </div>
                            <span v-else>—</span>
                        </template>
                        <template #cell.text_color="{ row }">
                            <div class="inline-flex items-center gap-1.5 min-w-0">
                                <span
                                    class="h-3.5 w-3.5 rounded-sm shrink-0 border"
                                    :style="{
                                        background: row.text_color || '#ffffff',
                                        borderColor: 'var(--color-border)',
                                    }"
                                />
                                <span class="truncate">{{ row.text_color || '#ffffff' }}</span>
                            </div>
                        </template>
                        <template #cell.active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
                        <template #cell.actions="{ row }">
                            <ListingRowActions
                                v-if="can.update"
                                :on-edit="() => startEditStatus(row)"
                                :on-delete="() => destroyStatus(row.id)"
                            />
                        </template>
                    </CollectimateDataTable>
                </div>

                <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="form-label mb-0">Entity Action Codes</div>
                        <div v-if="can.update" class="flex gap-2">
                            <Button size="sm" variant="secondary" @click="openActionCopyModal">Copy</Button>
                            <Button size="sm" @click="openActionModal">Add</Button>
                        </div>
                    </div>

                    <CollectimateDataTable
                        :value="actionRows"
                        :columns="actionColumns"
                        :rows="Math.max(actionRows.length, 1)"
                        :total-records="actionRows.length"
                        :first="0"
                        :paginator="true"
                    >
                        <template #cell.code="{ row }">{{ row.code || '—' }}</template>
                        <template #cell.classification="{ row }">
                            <span
                                class="inline-flex max-w-full truncate px-2 py-0.5 rounded text-xs font-medium border"
                                :style="classificationChipStyle(row.classification)"
                            >
                                {{ classificationLabel(row.classification) }}
                            </span>
                        </template>
                        <template #cell.active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
                        <template #cell.actions="{ row }">
                            <ListingRowActions
                                v-if="can.update"
                                :on-edit="() => startEditAction(row)"
                                :on-delete="() => destroyAction(row.id)"
                            />
                        </template>
                    </CollectimateDataTable>
                </div>
            </div>

            <div class="space-y-6 min-w-0">
                <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="flex items-center justify-between mb-3">
                        <div class="form-label mb-0">Campaigns ({{ entity.campaigns?.length ?? 0 }})</div>
                        <Button v-if="can.campaignsCreate" size="sm" @click="openCampaignModal">Add</Button>
                    </div>
                    <Table v-if="entity.campaigns?.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Accounts</TableHead>
                                <TableHead class="w-[120px]">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="campaign in entity.campaigns" :key="campaign.id">
                                <TableCell>{{ campaign.campaign_code }}</TableCell>
                                <TableCell>{{ campaign.name }}</TableCell>
                                <TableCell>{{ campaign.status }}</TableCell>
                                <TableCell>{{ campaign.accounts_count ?? 0 }}</TableCell>
                                <TableCell>
                                    <ListingRowActions
                                        :view-href="route('campaigns.show', campaign.id)"
                                        :on-delete="can.campaignsDelete ? () => destroyCampaign(campaign.id) : null"
                                    />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else style="color: var(--color-text-muted)">No campaigns yet.</p>
                </div>

                <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="form-label mb-0">Templates</div>
                        <div v-if="can.update" class="flex gap-2">
                            <Button size="sm" @click="openTemplateModal">Add</Button>
                        </div>
                    </div>

                    <CollectimateDataTable
                        :value="templateRows"
                        :columns="templateColumns"
                        :rows="Math.max(templateRows.length, 1)"
                        :total-records="templateRows.length"
                        :first="0"
                        :paginator="true"
                    >
                        <template #cell.types="{ row }">{{ formatTemplateTypes(row.types) }}</template>
                        <template #cell.body="{ row }">
                            <span class="block max-w-xs truncate" :title="row.body">
                                {{ truncateTemplateBody(row.body) }}
                            </span>
                        </template>
                        <template #cell.active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
                        <template #cell.actions="{ row }">
                            <ListingRowActions
                                v-if="can.update"
                                :on-edit="() => startEditTemplate(row)"
                                :on-delete="() => destroyTemplate(row.id)"
                            />
                        </template>
                    </CollectimateDataTable>
                </div>
            </div>
        </div>

        <Modal :show="showCampaignModal" max-width="lg" @close="closeCampaignModal">
            <form class="p-6 space-y-4" @submit.prevent="submitCampaign">
                <h2 class="text-lg font-semibold">Add campaign</h2>
                <div>
                    <label class="form-label block mb-1">Entity</label>
                    <div class="text-sm py-1">{{ entity.name }}</div>
                </div>
                <div>
                    <label class="form-label block mb-1">Code</label>
                    <Input v-model="campaignForm.campaign_code" class="w-full" />
                    <InputError :message="campaignForm.errors.campaign_code" />
                </div>
                <div>
                    <label class="form-label block mb-1">Name</label>
                    <Input v-model="campaignForm.name" class="w-full" />
                    <InputError :message="campaignForm.errors.name" />
                </div>
                <div>
                    <label class="form-label block mb-1">Status</label>
                    <Select v-model="campaignForm.status" :options="campaignStatuses" class="w-full" />
                    <InputError :message="campaignForm.errors.status" />
                </div>
                <div>
                    <label class="form-label block mb-1">Description</label>
                    <Textarea v-model="campaignForm.description" rows="3" class="w-full" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeCampaignModal">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="campaignForm.processing">Save</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showStatusModal" max-width="lg" @close="closeStatusModal">
            <form class="p-6 space-y-4" @submit.prevent="submitStatus">
                <h2 class="text-lg font-semibold">{{ editingStatusId ? 'Edit status' : 'Add status' }}</h2>
                <div>
                    <label class="form-label block mb-1">Name</label>
                    <Input v-model="statusForm.name" class="w-full" />
                    <InputError :message="statusForm.errors.name" />
                </div>
                <div>
                    <label class="form-label block mb-1">Code</label>
                    <Input v-model="statusForm.code" class="w-full" />
                </div>
                <div>
                    <label class="form-label block mb-1">Color</label>
                    <div class="flex items-center gap-2">
                        <Input v-model="statusForm.color" class="w-full" placeholder="#2F5D8C" />
                        <input
                            type="color"
                            class="h-9 w-10 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                            style="border-color: var(--color-border)"
                            :value="statusForm.color && /^#[0-9A-Fa-f]{6}$/.test(statusForm.color) ? statusForm.color : '#2F5D8C'"
                            @input="statusForm.color = $event.target.value"
                        />
                    </div>
                    <InputError :message="statusForm.errors.color" />
                </div>
                <div>
                    <label class="form-label block mb-1">Text color</label>
                    <div class="flex items-center gap-2">
                        <Input v-model="statusForm.text_color" class="w-full" placeholder="#ffffff" />
                        <input
                            type="color"
                            class="h-9 w-10 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                            style="border-color: var(--color-border)"
                            :value="
                                statusForm.text_color && /^#[0-9A-Fa-f]{6}$/.test(statusForm.text_color)
                                    ? statusForm.text_color
                                    : '#ffffff'
                            "
                            @input="statusForm.text_color = $event.target.value"
                        />
                    </div>
                    <InputError :message="statusForm.errors.text_color" />
                </div>
                <div>
                    <label class="form-label block mb-1">Order</label>
                    <Input v-model.number="statusForm.sort_order" type="number" min="0" class="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="status-active" v-model="statusForm.is_active" />
                    <label for="status-active" class="text-sm">Active</label>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeStatusModal">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="statusForm.processing">Save</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showStatusCopyModal" max-width="md" @close="closeStatusCopyModal">
            <form class="p-6 space-y-4" @submit.prevent="submitStatusCopy">
                <h2 class="text-lg font-semibold">Copy statuses</h2>
                <div v-if="copySourceOptions.length">
                    <label class="form-label block mb-1">Copy from entity</label>
                    <Select
                        v-model="statusCopyForm.source_entity_id"
                        :options="copySourceOptions"
                        placeholder="Select entity…"
                        class="w-full"
                    />
                    <InputError :message="statusCopyForm.errors.source_entity_id" />
                </div>
                <p v-else class="text-sm" style="color: var(--color-text-muted)">
                    Create another entity to copy from.
                </p>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeStatusCopyModal">Cancel</Button>
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="statusCopyForm.processing || !statusCopyForm.source_entity_id"
                    >
                        Copy
                    </Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showActionModal" max-width="lg" @close="closeActionModal">
            <form class="p-6 space-y-4" @submit.prevent="submitAction">
                <h2 class="text-lg font-semibold">
                    {{ editingActionId ? 'Edit action code' : 'Add action code' }}
                </h2>
                <div>
                    <label class="form-label block mb-1">Name</label>
                    <Input v-model="actionForm.name" class="w-full" />
                    <InputError :message="actionForm.errors.name" />
                </div>
                <div>
                    <label class="form-label block mb-1">Code</label>
                    <Input v-model="actionForm.code" class="w-full" />
                </div>
                <div>
                    <label class="form-label block mb-1">Classification</label>
                    <Select
                        v-model="actionForm.classification"
                        :options="classificationOptions"
                        class="w-full"
                    />
                    <InputError :message="actionForm.errors.classification" />
                </div>
                <div>
                    <label class="form-label block mb-1">Order</label>
                    <Input v-model.number="actionForm.sort_order" type="number" min="0" class="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="action-active" v-model="actionForm.is_active" />
                    <label for="action-active" class="text-sm">Active</label>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeActionModal">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="actionForm.processing">Save</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showActionCopyModal" max-width="md" @close="closeActionCopyModal">
            <form class="p-6 space-y-4" @submit.prevent="submitActionCopy">
                <h2 class="text-lg font-semibold">Copy action codes</h2>
                <div v-if="copySourceOptions.length">
                    <label class="form-label block mb-1">Copy from entity</label>
                    <Select
                        v-model="actionCopyForm.source_entity_id"
                        :options="copySourceOptions"
                        placeholder="Select entity…"
                        class="w-full"
                    />
                    <InputError :message="actionCopyForm.errors.source_entity_id" />
                </div>
                <p v-else class="text-sm" style="color: var(--color-text-muted)">
                    Create another entity to copy from.
                </p>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeActionCopyModal">Cancel</Button>
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="actionCopyForm.processing || !actionCopyForm.source_entity_id"
                    >
                        Copy
                    </Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showTemplateModal" max-width="lg" @close="closeTemplateModal">
            <form class="p-6 space-y-4" @submit.prevent="submitTemplate">
                <h2 class="text-lg font-semibold">
                    {{ editingTemplateId ? 'Edit template' : 'Add template' }}
                </h2>
                <div>
                    <label class="form-label block mb-2">Type</label>
                    <div class="flex flex-wrap gap-4">
                        <div
                            v-for="option in templateChannelOptions"
                            :key="option.value"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                :id="`template-type-${option.value}`"
                                :model-value="isTemplateChannelSelected(option.value)"
                                @update:model-value="(checked) => toggleTemplateChannel(option.value, checked)"
                            />
                            <label :for="`template-type-${option.value}`" class="text-sm">
                                {{ option.label }}
                            </label>
                        </div>
                    </div>
                    <InputError :message="templateForm.errors.types || templateForm.errors['types.0']" />
                </div>
                <div>
                    <label class="form-label block mb-1">Slug / Code</label>
                    <Input v-model="templateForm.slug" class="w-full" />
                    <InputError :message="templateForm.errors.slug" />
                </div>
                <div>
                    <label class="form-label block mb-1">Template</label>
                    <Textarea v-model="templateForm.body" rows="10" class="w-full" />
                    <p class="mt-1.5 text-sm" style="color: var(--color-text-muted)">
                        You can enclose variables in {}. If available, Collectimate will pick them up
                        (e.g. {account_name}). Fields from the Account record can also be used this way.
                    </p>
                    <InputError :message="templateForm.errors.body" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="template-active" v-model="templateForm.is_active" />
                    <label for="template-active" class="text-sm">Active</label>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeTemplateModal">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="templateForm.processing">Save</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6 space-y-3">
                <h2 class="text-lg font-semibold">Delete entity</h2>
                <p class="text-sm" style="color: var(--color-text-muted)">
                    This permanently deletes the entity, all campaigns, and all accounts under it.
                    Type <strong>{{ entity.name }}</strong> to confirm.
                </p>
                <div>
                    <Input v-model="deleteForm.confirmation_name" class="w-full" placeholder="Entity name" />
                    <InputError :message="deleteForm.errors.confirmation_name" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="showDeleteModal = false">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="!nameMatches || deleteForm.processing"
                        @click="confirmDelete"
                    >
                        Delete permanently
                    </Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
