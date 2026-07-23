<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link, router } from '@inertiajs/vue3';
import Column from 'primevue/column';
import Button from 'primevue/button';

const props = defineProps({ statuses: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'statuses.index');

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('statuses.export') + (params.toString() ? '?' + params.toString() : '');
}

function destroy(id) {
    if (confirm('Delete this status?')) router.delete(route('statuses.destroy', id));
}
</script>

<template>
    <Head title="Status Management" />
    <AppLayout>
        <template #header>Status Management</template>
        <ListingPage title="Statuses" :search="filters.search ?? ''" :can-export="can.export" :can-create="can.manage" :create-href="route('statuses.create')" :export-href="exportUrl()" @search="onSearch" @clear="onClear">
            <CollectimateDataTable :value="statuses.data" :rows="statuses.per_page" :total-records="statuses.total" :first="(statuses.current_page - 1) * statuses.per_page" :sort-field="filters.sort" :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null" @page="onPage" @sort="onSort">
                <Column field="name" header="Name" sortable />
                <Column field="slug" header="Slug" sortable />
                <Column field="category" header="Category" sortable />
                <Column field="sort_order" header="Order" sortable />
                <Column header="Active"><template #body="{ data }">{{ data.is_active ? 'Yes' : 'No' }}</template></Column>
                <Column header="Actions">
                    <template #body="{ data }">
                        <Link v-if="can.manage" :href="route('statuses.edit', data.id)" class="hover:underline me-2" style="color: var(--color-primary)">Edit</Link>
                        <Button v-if="can.manage" label="Delete" text severity="danger" size="small" @click="destroy(data.id)" />
                    </template>
                </Column>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
