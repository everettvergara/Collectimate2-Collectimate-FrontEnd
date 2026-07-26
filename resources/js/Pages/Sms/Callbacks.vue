<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Select } from '@/Components/ui/select';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    events: Object,
    filters: Object,
    filterOptions: Object,
    can: Object,
});

const { navigate, onPage, onSort, onSearch, onClear } = useListingNavigation(
    props.filters,
    'sms.callbacks.index',
);

const detailEvent = ref(null);

const columns = [
    { id: 'created_at', accessorKey: 'created_at', header: 'Received', sortable: true },
    { id: 'event_type', accessorKey: 'event_type', header: 'Event type', sortable: true },
    { id: 'response_type', accessorKey: 'response_type', header: 'Response', sortable: true },
    { id: 'device_id', accessorKey: 'device_id', header: 'Device', sortable: true },
    { id: 'event_id', accessorKey: 'event_id', header: 'Event ID', sortable: true },
    { id: 'event_timestamp', accessorKey: 'event_timestamp', header: 'Event time', sortable: true },
    { id: 'payload_preview', header: 'Payload' },
    { id: 'actions', header: '' },
];

function exportUrl() {
    const params = new URLSearchParams();
    Object.entries(props.filters ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, value);
        }
    });
    const qs = params.toString();
    return route('sms.callbacks.export') + (qs ? `?${qs}` : '');
}

function applyFilter(key, value) {
    navigate({ [key]: value || undefined, page: 1 });
}

function openDetail(row) {
    detailEvent.value = row;
}

function closeDetail() {
    detailEvent.value = null;
}

function payloadJson(payload) {
    try {
        return JSON.stringify(payload ?? {}, null, 2);
    } catch {
        return String(payload ?? '');
    }
}
</script>

<template>
    <Head title="SMS Callbacks" />
    <AppLayout>
        <template #header>SMS Callbacks</template>
        <ListingPage
            title="SMS Callbacks"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :export-href="exportUrl()"
            @search="onSearch"
            @clear="onClear"
        >
            <template #filters>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Event type</label>
                    <Select
                        :model-value="filters.event_type ?? null"
                        :options="filterOptions?.eventTypes ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="(v) => applyFilter('event_type', v)"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Response</label>
                    <Select
                        :model-value="filters.response_type ?? null"
                        :options="filterOptions?.responseTypes ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="(v) => applyFilter('response_type', v)"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Device</label>
                    <Select
                        :model-value="filters.device_id ?? null"
                        :options="filterOptions?.devices ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="(v) => applyFilter('device_id', v)"
                    />
                </div>
            </template>

            <CollectimateDataTable
                :value="events.data"
                :columns="columns"
                :rows="events.per_page"
                :total-records="events.total"
                :first="(events.current_page - 1) * events.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.payload_preview="{ row }">
                    <span class="font-mono text-xs break-all" style="color: var(--color-text-muted)">
                        {{ row.payload_preview }}
                    </span>
                </template>
                <template #cell.actions="{ row }">
                    <Button type="button" variant="secondary" size="sm" @click="openDetail(row)">
                        View
                    </Button>
                </template>
            </CollectimateDataTable>
        </ListingPage>

        <Modal :show="!!detailEvent" max-width="2xl" @close="closeDetail">
            <div v-if="detailEvent" class="p-6 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Callback event</h2>
                        <p class="text-sm mt-1 font-mono" style="color: var(--color-text-muted)">
                            {{ detailEvent.event_id }}
                        </p>
                    </div>
                    <Button type="button" variant="secondary" size="sm" @click="closeDetail">Close</Button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="form-label mb-1">Event type</div>
                        <div>{{ detailEvent.event_type || '—' }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Response</div>
                        <div>{{ detailEvent.response_type || '—' }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Device</div>
                        <div>{{ detailEvent.device_id || '—' }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Received</div>
                        <div>{{ detailEvent.created_at || '—' }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Event time</div>
                        <div>{{ detailEvent.event_timestamp || '—' }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Processed</div>
                        <div>{{ detailEvent.processed_at || '—' }}</div>
                    </div>
                </div>
                <div>
                    <div class="form-label mb-1">Payload</div>
                    <pre
                        class="text-xs p-3 rounded border overflow-auto max-h-96 font-mono whitespace-pre-wrap break-all"
                        style="background: var(--color-bg-subtle); border-color: var(--color-border)"
                    >{{ payloadJson(detailEvent.payload) }}</pre>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
