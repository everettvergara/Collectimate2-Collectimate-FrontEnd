<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link } from '@inertiajs/vue3';
import Column from 'primevue/column';

const props = defineProps({ entities: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'entities.index');

function exportUrl() {
    const params = new URLSearchParams(props.filters ?? {});
    return route('entities.export') + (params.toString() ? '?' + params.toString() : '');
}
</script>

<template>
    <Head title="Entities" />
    <AppLayout>
        <template #header>Entities</template>
        <ListingPage title="Entities" :search="filters.search ?? ''" :can-export="can.export" :can-create="can.create" :create-href="route('entities.create')" :export-href="exportUrl()" @search="onSearch" @clear="onClear">
            <CollectimateDataTable :value="entities.data" :rows="entities.per_page" :total-records="entities.total" :first="(entities.current_page - 1) * entities.per_page" :sort-field="filters.sort" :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null" @page="onPage" @sort="onSort">
                <Column field="entity_code" header="Code" sortable />
                <Column field="name" header="Name" sortable />
                <Column header="Campaigns"><template #body="{ data }">{{ data.campaigns_count }}</template></Column>
                <Column header="Status"><template #body="{ data }">{{ data.status?.name }}</template></Column>
                <Column header="Actions">
                    <template #body="{ data }">
                        <Link :href="route('entities.show', data.id)" class="hover:underline me-2" style="color: var(--color-primary)">View</Link>
                        <Link :href="route('entities.edit', data.id)" class="hover:underline" style="color: var(--color-primary)">Edit</Link>
                    </template>
                </Column>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
