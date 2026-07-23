<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link } from '@inertiajs/vue3';
import Column from 'primevue/column';

const props = defineProps({ campaigns: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'campaigns.index');

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
            <CollectimateDataTable :value="campaigns.data" :rows="campaigns.per_page" :total-records="campaigns.total" :first="(campaigns.current_page - 1) * campaigns.per_page" :sort-field="filters.sort" :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null" @page="onPage" @sort="onSort">
                <Column field="campaign_code" header="Code" sortable />
                <Column field="name" header="Name" sortable />
                <Column header="Entity"><template #body="{ data }">{{ data.entity?.name }}</template></Column>
                <Column field="status" header="Status" sortable />
                <Column header="Accounts"><template #body="{ data }">{{ data.accounts_count }}</template></Column>
                <Column header="Actions">
                    <template #body="{ data }">
                        <Link :href="route('campaigns.show', data.id)" class="hover:underline me-2" style="color: var(--color-primary)">View</Link>
                        <Link :href="route('campaigns.edit', data.id)" class="hover:underline" style="color: var(--color-primary)">Edit</Link>
                    </template>
                </Column>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
