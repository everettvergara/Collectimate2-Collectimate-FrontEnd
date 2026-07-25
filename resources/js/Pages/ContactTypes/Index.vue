<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ contactTypes: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'contact-types.index');

const columns = [
    { id: 'name', accessorKey: 'name', header: 'Name', sortable: true },
    { id: 'code', accessorKey: 'code', header: 'Code', sortable: true },
];

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('contact-types.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Contact Types" />
    <AppLayout>
        <template #header>Contact Types</template>
        <ListingPage
            title="Contact Types"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :can-create="false"
            :export-href="exportUrl()"
            @search="onSearch"
            @clear="onClear"
        >
            <CollectimateDataTable
                :value="contactTypes.data"
                :columns="columns"
                :rows="contactTypes.per_page"
                :total-records="contactTypes.total"
                :first="(contactTypes.current_page - 1) * contactTypes.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'desc' ? -1 : filters.direction === 'asc' ? 1 : null"
                @page="onPage"
                @sort="onSort"
            />
        </ListingPage>
    </AppLayout>
</template>
