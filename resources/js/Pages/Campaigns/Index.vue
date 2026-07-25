<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ campaigns: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'campaigns.index');

const columns = [
    { id: 'campaign_code', accessorKey: 'campaign_code', header: 'Code', sortable: true },
    { id: 'name', accessorKey: 'name', header: 'Name', sortable: true },
    { id: 'entity', header: 'Entity' },
    { id: 'status', accessorKey: 'status', header: 'Status', sortable: true },
    { id: 'accounts', header: 'Accounts' },
    { id: 'actions', header: 'Actions' },
];

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('campaigns.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Campaigns" />
    <AppLayout>
        <template #header>Campaigns</template>
        <ListingPage title="Campaigns" :search="filters.search ?? ''" :can-export="can.export" :can-create="can.create" :create-href="route('campaigns.create')" :export-href="exportUrl()" @search="onSearch" @clear="onClear">
            <CollectimateDataTable
                :value="campaigns.data"
                :columns="columns"
                :rows="campaigns.per_page"
                :total-records="campaigns.total"
                :first="(campaigns.current_page - 1) * campaigns.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.entity="{ row }">{{ row.entity?.name }}</template>
                <template #cell.accounts="{ row }">{{ row.accounts_count }}</template>
                <template #cell.actions="{ row }">
                    <ListingRowActions
                        :view-href="route('campaigns.show', row.id)"
                        :edit-href="route('campaigns.edit', row.id)"
                    />
                </template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
