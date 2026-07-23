<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';
import Column from 'primevue/column';

const props = defineProps({ logs: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'audit-logs.index');

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
            <CollectimateDataTable :value="logs.data" :rows="logs.per_page" :total-records="logs.total" :first="(logs.current_page - 1) * logs.per_page" :sort-field="filters.sort" :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null" @page="onPage" @sort="onSort">
                <Column field="created_at" header="Date" sortable />
                <Column header="User"><template #body="{ data }">{{ data.user?.username }}</template></Column>
                <Column field="action" header="Action" sortable />
                <Column header="Subject"><template #body="{ data }">{{ data.subject_type }} #{{ data.subject_id }}</template></Column>
                <Column header="Campaign"><template #body="{ data }">{{ data.campaign?.name }}</template></Column>
                <Column field="ip" header="IP" />
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
