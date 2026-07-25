<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({ addressTypes: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'address-types.index');

const columns = [
    { id: 'name', accessorKey: 'name', header: 'Name', sortable: true },
    { id: 'code', accessorKey: 'code', header: 'Code', sortable: true },
    { id: 'sort_order', accessorKey: 'sort_order', header: 'Order', sortable: true },
    { id: 'default', header: 'Default' },
    { id: 'active', header: 'Active' },
    { id: 'actions', header: 'Actions' },
];

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('address-types.export') + (params.toString() ? '?' + params.toString() : '');
}

function destroy(id) {
    if (confirm('Delete this address type?')) router.delete(route('address-types.destroy', id));
}
</script>

<template>
    <Head title="Address Types" />
    <AppLayout>
        <template #header>Address Types</template>
        <ListingPage
            title="Address Types"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :can-create="can.manage"
            :create-href="route('address-types.create')"
            :export-href="exportUrl()"
            @search="onSearch"
            @clear="onClear"
        >
            <CollectimateDataTable
                :value="addressTypes.data"
                :columns="columns"
                :rows="addressTypes.per_page"
                :total-records="addressTypes.total"
                :first="(addressTypes.current_page - 1) * addressTypes.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'desc' ? -1 : filters.direction === 'asc' ? 1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.default="{ row }">{{ row.is_default ? 'Yes' : 'No' }}</template>
                <template #cell.active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
                <template #cell.actions="{ row }">
                    <ListingRowActions
                        v-if="can.manage"
                        :edit-href="route('address-types.edit', row.id)"
                        :on-delete="row.is_default ? null : () => destroy(row.id)"
                    />
                </template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
