<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link } from '@inertiajs/vue3';
import Column from 'primevue/column';

const props = defineProps({ accounts: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'accounts.index');

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('accounts.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Account Master" />
    <AppLayout>
        <template #header>Account Master</template>
        <ListingPage title="Accounts" :search="filters.search ?? ''" :can-export="can.export" :can-create="can.create" :create-href="route('accounts.create')" :export-href="exportUrl()" @search="onSearch" @clear="onClear">
            <CollectimateDataTable :value="accounts.data" :rows="accounts.per_page" :total-records="accounts.total" :first="(accounts.current_page - 1) * accounts.per_page" :sort-field="filters.sort" :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null" @page="onPage" @sort="onSort">
                <Column field="account_number" header="Account #" sortable />
                <Column header="Entity"><template #body="{ data }">{{ data.campaign?.entity?.name }}</template></Column>
                <Column header="Campaign"><template #body="{ data }">{{ data.campaign?.name }}</template></Column>
                <Column field="product" header="Product" sortable />
                <Column field="balance" header="Balance" sortable />
                <Column header="Status"><template #body="{ data }">{{ data.status?.name }}</template></Column>
                <Column header="Actions">
                    <template #body="{ data }">
                        <Link :href="route('accounts.show', data.id)" class="hover:underline me-2" style="color: var(--color-primary)">View</Link>
                        <Link :href="route('accounts.edit', data.id)" class="hover:underline" style="color: var(--color-primary)">Edit</Link>
                    </template>
                </Column>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
