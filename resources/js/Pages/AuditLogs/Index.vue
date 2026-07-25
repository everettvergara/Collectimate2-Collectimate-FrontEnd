<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ logs: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'audit-logs.index');

const columns = [
    { id: 'created_at', accessorKey: 'created_at', header: 'Date', sortable: true },
    { id: 'user', header: 'User' },
    { id: 'action', accessorKey: 'action', header: 'Action', sortable: true },
    { id: 'subject', header: 'Subject' },
    { id: 'campaign', header: 'Campaign' },
    { id: 'ip', accessorKey: 'ip', header: 'IP' },
];

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('audit-logs.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Audit Logs" />
    <AppLayout>
        <template #header>Audit Logs</template>
        <ListingPage title="Audit Logs" :search="filters.search ?? ''" :can-export="can.export" :export-href="exportUrl()" @search="onSearch" @clear="onClear">
            <CollectimateDataTable
                :value="logs.data"
                :columns="columns"
                :rows="logs.per_page"
                :total-records="logs.total"
                :first="(logs.current_page - 1) * logs.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.user="{ row }">{{ row.user?.username }}</template>
                <template #cell.subject="{ row }">{{ row.subject_type }} #{{ row.subject_id }}</template>
                <template #cell.campaign="{ row }">{{ row.campaign?.name }}</template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
