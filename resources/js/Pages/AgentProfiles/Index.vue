<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link } from '@inertiajs/vue3';
import Column from 'primevue/column';

const props = defineProps({ profiles: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'agent-profiles.index');

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
            <CollectimateDataTable :value="profiles.data" :rows="profiles.per_page" :total-records="profiles.total" :first="(profiles.current_page - 1) * profiles.per_page" :sort-field="filters.sort" :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null" @page="onPage" @sort="onSort">
                <Column field="employee_number" header="Employee #" sortable />
                <Column field="display_name" header="Display name" sortable />
                <Column field="email" header="Email" />
                <Column field="department" header="Department" />
                <Column field="status" header="Status" sortable />
                <Column header="Actions">
                    <template #body="{ data }">
                        <Link :href="route('agent-profiles.show', data.id)" class="hover:underline me-2" style="color: var(--color-primary)">View</Link>
                        <Link :href="route('agent-profiles.edit', data.id)" class="hover:underline" style="color: var(--color-primary)">Edit</Link>
                    </template>
                </Column>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
