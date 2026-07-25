<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    users: Object,
    filters: Object,
    can: Object,
});

const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'users.index');

const columns = [
    { id: 'username', accessorKey: 'username', header: 'Username', sortable: true },
    { id: 'email', accessorKey: 'email', header: 'Email', sortable: true },
    { id: 'first_name', accessorKey: 'first_name', header: 'First name', sortable: true },
    { id: 'last_name', accessorKey: 'last_name', header: 'Last name', sortable: true },
    { id: 'status', accessorKey: 'status', header: 'Status', sortable: true },
    { id: 'role', header: 'Role' },
    { id: 'actions', header: 'Actions' },
];

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('users.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <template #header>Users</template>

        <ListingPage
            title="Users"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :can-create="can.create"
            :create-href="route('users.create')"
            :export-href="exportUrl()"
            @search="onSearch"
            @clear="onClear"
        >
            <CollectimateDataTable
                :value="users.data"
                :columns="columns"
                :rows="users.per_page"
                :total-records="users.total"
                :first="(users.current_page - 1) * users.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.role="{ row }">{{ row.role?.name }}</template>
                <template #cell.actions="{ row }">
                    <ListingRowActions :edit-href="route('users.edit', row.id)" />
                </template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
