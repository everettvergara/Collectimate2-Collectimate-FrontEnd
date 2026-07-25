<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ entities: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'entities.index');

const columns = [
    { id: 'logo', header: 'Logo' },
    { id: 'entity_code', accessorKey: 'entity_code', header: 'Code', sortable: true },
    { id: 'name', accessorKey: 'name', header: 'Name', sortable: true },
    { id: 'campaigns', header: 'Campaigns' },
    { id: 'actions', header: 'Actions' },
];

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
            <CollectimateDataTable
                :value="entities.data"
                :columns="columns"
                :rows="entities.per_page"
                :total-records="entities.total"
                :first="(entities.current_page - 1) * entities.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.logo="{ row }">
                    <div
                        class="h-8 w-8 rounded-md overflow-hidden border flex items-center justify-center text-xs font-medium"
                        style="background: var(--color-bg); border-color: var(--color-border); color: var(--color-text-muted)"
                    >
                        <img
                            v-if="row.logo_url"
                            :src="row.logo_url"
                            :alt="`${row.name} logo`"
                            class="h-full w-full object-contain"
                        />
                        <span v-else>{{ (row.name || '?').slice(0, 1).toUpperCase() }}</span>
                    </div>
                </template>
                <template #cell.campaigns="{ row }">{{ row.campaigns_count }}</template>
                <template #cell.actions="{ row }">
                    <ListingRowActions
                        :view-href="route('entities.show', row.id)"
                    />
                </template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
