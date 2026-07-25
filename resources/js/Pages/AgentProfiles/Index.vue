<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ profiles: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'agent-profiles.index');

const columns = [
    { id: 'employee_number', accessorKey: 'employee_number', header: 'Employee #', sortable: true },
    { id: 'display_name', accessorKey: 'display_name', header: 'Display name', sortable: true },
    { id: 'email', accessorKey: 'email', header: 'Email' },
    { id: 'department', accessorKey: 'department', header: 'Department' },
    { id: 'status', accessorKey: 'status', header: 'Status', sortable: true },
    { id: 'actions', header: 'Actions' },
];

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('agent-profiles.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Agent Profiles" />
    <AppLayout>
        <template #header>Agent Profiles</template>
        <ListingPage title="Agent Profiles" :search="filters.search ?? ''" :can-export="can.export" :can-create="can.create" :create-href="route('agent-profiles.create')" :export-href="exportUrl()" @search="onSearch" @clear="onClear">
            <CollectimateDataTable
                :value="profiles.data"
                :columns="columns"
                :rows="profiles.per_page"
                :total-records="profiles.total"
                :first="(profiles.current_page - 1) * profiles.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.actions="{ row }">
                    <ListingRowActions
                        :view-href="route('agent-profiles.show', row.id)"
                        :edit-href="route('agent-profiles.edit', row.id)"
                    />
                </template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
