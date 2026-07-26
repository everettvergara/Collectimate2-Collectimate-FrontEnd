<script setup>
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    batch: Object,
    items: Object,
    filters: Object,
    filterOptions: Object,
    can: Object,
});

function navigateShow(overrides = {}) {
    router.get(
        route('sms.batches.show', props.batch.id),
        { ...props.filters, ...overrides },
        { preserveState: true, replace: true },
    );
}
function onPageShow(event) {
    navigateShow({ page: event.pageIndex + 1 });
}
function onSortShow(event) {
    navigateShow({
        sort: event.id,
        direction: event.desc ? 'desc' : 'asc',
        page: 1,
    });
}
function onSearchShow(search) {
    navigateShow({ search: search || undefined, page: 1 });
}
function onClearShow() {
    router.get(route('sms.batches.show', props.batch.id), {}, { preserveState: true, replace: true });
}

const priorityForm = useForm({
    priority: props.batch.priority,
});
watch(() => props.batch.priority, (v) => {
    priorityForm.priority = v;
});

const editItem = ref(null);
const viewItem = ref(null);
const itemForm = useForm({
    recipient: '',
    message: '',
});

const columns = [
    { id: 'id', accessorKey: 'id', header: '#', sortable: true },
    { id: 'status', accessorKey: 'status', header: 'Status', sortable: true },
    { id: 'account_label', header: 'Account' },
    { id: 'recipient', accessorKey: 'recipient', header: 'Recipient', sortable: true },
    { id: 'message_preview', header: 'Message' },
    { id: 'runtime_device_id', header: 'Device' },
    { id: 'actions', header: 'Actions' },
];

function applyItemStatus(value) {
    navigateShow({ item_status: value || undefined, page: 1 });
}

function savePriority() {
    priorityForm.put(route('sms.batches.update', props.batch.id), { preserveScroll: true });
}

function pauseBatch() {
    router.post(route('sms.batches.pause', props.batch.id), {}, { preserveScroll: true });
}
function resumeBatch() {
    router.post(route('sms.batches.resume', props.batch.id), {}, { preserveScroll: true });
}
function bumpPriority(direction) {
    router.post(route('sms.batches.priority', props.batch.id), { direction }, { preserveScroll: true });
}
function cancelBatch() {
    if (!confirm(`Cancel remaining queued items in batch #${props.batch.id}? Linked SMS Send activities will be deleted.`)) return;
    router.post(route('sms.batches.cancel', props.batch.id), {}, { preserveScroll: true });
}
function deleteBatch() {
    if (!confirm(`Permanently delete batch #${props.batch.id} and all its queue items?`)) return;
    router.delete(route('sms.batches.destroy', props.batch.id));
}

function openView(row) {
    viewItem.value = row;
}
function closeView() {
    viewItem.value = null;
}
function openEdit(row) {
    editItem.value = row;
    itemForm.clearErrors();
    itemForm.recipient = row.recipient || '';
    itemForm.message = row.message || '';
}
function closeEdit() {
    editItem.value = null;
    itemForm.reset();
    itemForm.clearErrors();
}
function saveItem() {
    if (!editItem.value) return;
    itemForm.put(route('sms.batches.items.update', {
        smsBatch: props.batch.id,
        item: editItem.value.id,
    }), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
}
function cancelItem(row) {
    if (!confirm(`Cancel queue item #${row.id}? Linked SMS Send activity will be deleted.`)) return;
    router.post(route('sms.batches.items.cancel', {
        smsBatch: props.batch.id,
        item: row.id,
    }), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`SMS Batch ${batch.id}`" />
    <AppLayout>
        <template #header>SMS Batch {{ batch.id }}</template>

        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Link :href="route('sms.batches.index')" class="text-sm underline">← SMS Batches</Link>
                    <h1 class="page-title mt-1">Batch {{ batch.id }}</h1>
                    <p class="text-sm mt-1" style="color: var(--color-text-muted)">
                        <span :style="batch.status === 'cancelled' ? { color: '#b91c1c' } : undefined">{{ batch.status }}</span>
                        · {{ batch.source }} · created {{ batch.created_at || '—' }}
                        <span v-if="batch.created_by"> · by {{ batch.created_by }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="can.manage && batch.can_pause"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="pauseBatch"
                    >Pause</Button>
                    <Button
                        v-if="can.manage && batch.can_resume"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="resumeBatch"
                    >Resume</Button>
                    <Button
                        v-if="can.manage && batch.can_priority"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="bumpPriority('up')"
                    >Prio↑</Button>
                    <Button
                        v-if="can.manage && batch.can_priority"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="bumpPriority('down')"
                    >Prio↓</Button>
                    <Button
                        v-if="can.cancel && batch.can_cancel"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="cancelBatch"
                    >Cancel</Button>
                    <Button
                        v-if="can.manage && batch.can_delete"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="deleteBatch"
                    >Delete</Button>
                </div>
            </div>

            <div
                class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 rounded border p-4"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div>
                    <div class="text-xs" style="color: var(--color-text-muted)">Queued</div>
                    <div class="font-semibold">{{ batch.queued }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--color-text-muted)">Sending</div>
                    <div class="font-semibold">{{ batch.sending }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--color-text-muted)">Sent</div>
                    <div class="font-semibold">{{ batch.sent }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--color-text-muted)">Failed</div>
                    <div class="font-semibold">{{ batch.failed }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--color-text-muted)">Cancelled</div>
                    <div class="font-semibold">{{ batch.cancelled }}</div>
                </div>
                <div>
                    <div class="text-xs" style="color: var(--color-text-muted)">Total</div>
                    <div class="font-semibold">{{ batch.total }}</div>
                </div>
                <div v-if="can.manage" class="sm:col-span-2 lg:col-span-1 space-y-1">
                    <label class="form-label block">Priority</label>
                    <div class="flex gap-2">
                        <Input v-model="priorityForm.priority" type="number" min="1" max="1000" class="w-24" />
                        <Button type="button" size="sm" :disabled="priorityForm.processing" @click="savePriority">
                            Save
                        </Button>
                    </div>
                    <InputError :message="priorityForm.errors.priority" />
                </div>
                <div v-else>
                    <div class="text-xs" style="color: var(--color-text-muted)">Priority</div>
                    <div class="font-semibold">{{ batch.priority }}</div>
                </div>
            </div>

            <div
                class="rounded border p-4 space-y-1"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div class="text-xs font-medium" style="color: var(--color-text-muted)">Message body</div>
                <pre class="text-sm whitespace-pre-wrap break-words font-sans">{{ batch.message_body || '—' }}</pre>
            </div>

            <ListingPage
                title="Queue items"
                :search="filters.search ?? ''"
                @search="onSearchShow"
                @clear="onClearShow"
            >
                <template #filters>
                    <div class="min-w-[10rem]">
                        <label class="form-label block mb-1">Item status</label>
                        <Select
                            :model-value="filters.item_status ?? null"
                            :options="filterOptions?.itemStatuses ?? []"
                            option-label="name"
                            option-value="id"
                            placeholder="All"
                            show-clear
                            class="w-full min-w-[10rem]"
                            @update:model-value="applyItemStatus"
                        />
                    </div>
                </template>

                <CollectimateDataTable
                    :value="items.data"
                    :columns="columns"
                    :rows="items.per_page"
                    :total-records="items.total"
                    :first="(items.current_page - 1) * items.per_page"
                    :sort-field="filters.sort"
                    :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                    @page="onPageShow"
                    @sort="onSortShow"
                >
                    <template #cell.message_preview="{ row }">
                        <span class="text-xs" style="color: var(--color-text-muted)">{{ row.message_preview }}</span>
                    </template>
                    <template #cell.actions="{ row }">
                        <div class="flex flex-wrap gap-2 whitespace-nowrap">
                            <button type="button" class="underline text-sm" @click="openView(row)">View</button>
                            <button
                                v-if="can.manage && row.can_edit"
                                type="button"
                                class="underline text-sm"
                                @click="openEdit(row)"
                            >Edit</button>
                            <button
                                v-if="can.cancel && row.can_cancel"
                                type="button"
                                class="underline text-sm"
                                @click="cancelItem(row)"
                            >Cancel</button>
                        </div>
                    </template>
                </CollectimateDataTable>
            </ListingPage>
        </div>

        <Modal :show="!!viewItem" max-width="2xl" @close="closeView">
            <div v-if="viewItem" class="p-6 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold">Queue item #{{ viewItem.id }}</h2>
                    <Button type="button" variant="secondary" size="sm" @click="closeView">Close</Button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div><div class="form-label mb-1">Status</div><div>{{ viewItem.status }}</div></div>
                    <div><div class="form-label mb-1">Recipient</div><div>{{ viewItem.recipient || '—' }}</div></div>
                    <div><div class="form-label mb-1">Account</div><div>{{ viewItem.account_label || '—' }}</div></div>
                    <div><div class="form-label mb-1">Device</div><div>{{ viewItem.runtime_device_id || viewItem.assigned_device || '—' }}</div></div>
                    <div><div class="form-label mb-1">Reference</div><div class="font-mono text-xs break-all">{{ viewItem.reference || '—' }}</div></div>
                    <div><div class="form-label mb-1">Error</div><div>{{ viewItem.error_message || '—' }}</div></div>
                </div>
                <div>
                    <div class="form-label mb-1">Message</div>
                    <pre class="text-sm p-3 rounded border whitespace-pre-wrap break-words" style="background: var(--color-bg-subtle); border-color: var(--color-border)">{{ viewItem.message || '—' }}</pre>
                </div>
            </div>
        </Modal>

        <Modal :show="!!editItem" max-width="lg" @close="closeEdit">
            <form v-if="editItem" class="p-6 space-y-4" @submit.prevent="saveItem">
                <h2 class="text-lg font-semibold">Edit queue item #{{ editItem.id }}</h2>
                <div>
                    <label class="form-label block mb-1">Recipient</label>
                    <Input v-model="itemForm.recipient" class="w-full" />
                    <InputError :message="itemForm.errors.recipient" />
                </div>
                <div>
                    <label class="form-label block mb-1">Message</label>
                    <Textarea v-model="itemForm.message" rows="6" class="w-full" />
                    <InputError :message="itemForm.errors.message || itemForm.errors.item" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeEdit">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="itemForm.processing">Save</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
