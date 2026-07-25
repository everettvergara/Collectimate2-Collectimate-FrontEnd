<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ activityTypes: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'activity-types.index');

const columns = [
    { id: 'name', accessorKey: 'name', header: 'Name', sortable: true },
    { id: 'code', accessorKey: 'code', header: 'Code', sortable: true },
    { id: 'sort_order', accessorKey: 'sort_order', header: 'Order', sortable: true },
    { id: 'default', header: 'Default' },
    { id: 'active', header: 'Active' },
];

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('activity-types.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Activity Types" />
    <AppLayout>
        <template #header>Activity Types</template>
        <ListingPage
            title="Activity Types"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :can-create="false"
            :export-href="exportUrl()"
            @search="onSearch"
            @clear="onClear"
        >
            <CollectimateDataTable
                :value="activityTypes.data"
                :columns="columns"
                :rows="activityTypes.per_page"
                :total-records="activityTypes.total"
                :first="(activityTypes.current_page - 1) * activityTypes.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'desc' ? -1 : filters.direction === 'asc' ? 1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.default="{ row }">{{ row.is_default ? 'Yes' : 'No' }}</template>
                <template #cell.active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
