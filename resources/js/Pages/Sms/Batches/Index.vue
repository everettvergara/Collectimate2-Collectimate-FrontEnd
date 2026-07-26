<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { Select } from '@/Components/ui/select';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    batches: Object,
    filters: Object,
    filterOptions: Object,
    can: Object,
});

const { navigate, onPage, onSort, onSearch, onClear } = useListingNavigation(
    props.filters,
    'sms.batches.index',
);

const columns = [
    { id: 'id', accessorKey: 'id', header: 'Batch', sortable: true },
    { id: 'status', accessorKey: 'status', header: 'Status', sortable: true },
    { id: 'priority', accessorKey: 'priority', header: 'Prio', sortable: true },
    { id: 'source', accessorKey: 'source', header: 'Source', sortable: true },
    { id: 'queued', accessorKey: 'queued', header: 'Queued', sortable: true },
    { id: 'sending', accessorKey: 'sending', header: 'Sending', sortable: true },
    { id: 'sent', accessorKey: 'sent', header: 'Sent', sortable: true },
    { id: 'failed', accessorKey: 'failed', header: 'Failed', sortable: true },
    { id: 'cancelled', accessorKey: 'cancelled', header: 'Cancelled', sortable: true },
    { id: 'message_preview', header: 'Message' },
    { id: 'created_at', accessorKey: 'created_at', header: 'Created', sortable: true },
    { id: 'actions', header: 'Actions' },
];

function exportUrl() {
    const params = new URLSearchParams();
    Object.entries(props.filters ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, value);
        }
    });
    const qs = params.toString();
    return route('sms.batches.export') + (qs ? `?${qs}` : '');
}

function applyFilter(key, value) {
    navigate({ [key]: value || undefined, page: 1 });
}

function pauseBatch(batch) {
    router.post(route('sms.batches.pause', batch.id), {}, { preserveScroll: true });
}
function resumeBatch(batch) {
    router.post(route('sms.batches.resume', batch.id), {}, { preserveScroll: true });
}
function bumpPriority(batch, direction) {
    router.post(route('sms.batches.priority', batch.id), { direction }, { preserveScroll: true });
}
function cancelBatch(batch) {
    if (!confirm(`Cancel remaining queued items in batch #${batch.id}? Linked SMS Send activities will be deleted.`)) return;
    router.post(route('sms.batches.cancel', batch.id), {}, { preserveScroll: true });
}
function deleteBatch(batch) {
    if (!confirm(`Permanently delete batch #${batch.id} and all its queue items?`)) return;
    router.delete(route('sms.batches.destroy', batch.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="SMS Batches" />
    <AppLayout>
        <template #header>SMS Batches</template>
        <ListingPage
            title="SMS Batches"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :export-href="exportUrl()"
            @search="onSearch"
            @clear="onClear"
        >
            <template #filters>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Status</label>
                    <Select
                        :model-value="filters.status ?? null"
                        :options="filterOptions?.statuses ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="(v) => applyFilter('status', v)"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Source</label>
                    <Select
                        :model-value="filters.source ?? null"
                        :options="filterOptions?.sources ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="(v) => applyFilter('source', v)"
                    />
                </div>
            </template>

            <CollectimateDataTable
                :value="batches.data"
                :columns="columns"
                :rows="batches.per_page"
                :total-records="batches.total"
                :first="(batches.current_page - 1) * batches.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.id="{ row }">
                    <Link :href="route('sms.batches.show', row.id)" class="underline font-medium">
                        {{ row.id }}
                    </Link>
                </template>
                <template #cell.status="{ row }">
                    <span
                        :style="row.status === 'cancelled' ? { color: '#b91c1c' } : undefined"
                    >
                        {{ row.status }}
                    </span>
                </template>
                <template #cell.message_preview="{ row }">
                    <span class="text-xs" style="color: var(--color-text-muted)">{{ row.message_preview }}</span>
                </template>
                <template #cell.actions="{ row }">
                    <div class="flex flex-wrap gap-2 whitespace-nowrap">
                        <Link :href="route('sms.batches.show', row.id)" class="underline text-sm">View</Link>
                        <button
                            v-if="can.manage && row.can_pause"
                            type="button"
                            class="underline text-sm"
                            @click="pauseBatch(row)"
                        >Pause</button>
                        <button
                            v-if="can.manage && row.can_resume"
                            type="button"
                            class="underline text-sm"
                            @click="resumeBatch(row)"
                        >Resume</button>
                        <button
                            v-if="can.manage && row.can_priority"
                            type="button"
                            class="underline text-sm"
                            @click="bumpPriority(row, 'up')"
                        >Prio↑</button>
                        <button
                            v-if="can.manage && row.can_priority"
                            type="button"
                            class="underline text-sm"
                            @click="bumpPriority(row, 'down')"
                        >Prio↓</button>
                        <button
                            v-if="can.cancel && row.can_cancel"
                            type="button"
                            class="underline text-sm"
                            @click="cancelBatch(row)"
                        >Cancel</button>
                        <button
                            v-if="can.manage && row.can_delete"
                            type="button"
                            class="underline text-sm"
                            @click="deleteBatch(row)"
                        >Delete</button>
                    </div>
                </template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
