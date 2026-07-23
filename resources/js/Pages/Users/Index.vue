<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link } from '@inertiajs/vue3';
import Column from 'primevue/column';

const props = defineProps({
    users: Object,
    filters: Object,
    can: Object,
});

const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'users.index');

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
                :rows="users.per_page"
                :total-records="users.total"
                :first="(users.current_page - 1) * users.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <Column field="username" header="Username" sortable />
                <Column field="email" header="Email" sortable />
                <Column field="first_name" header="First name" sortable />
                <Column field="last_name" header="Last name" sortable />
                <Column field="status" header="Status" sortable />
                <Column header="Role">
                    <template #body="{ data }">{{ data.role?.name }}</template>
                </Column>
                <Column header="Actions">
                    <template #body="{ data }">
                        <Link :href="route('users.edit', data.id)" class="hover:underline" style="color: var(--color-primary)">Edit</Link>
                    </template>
                </Column>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
