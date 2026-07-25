<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { Button } from '@/Components/ui/button';
import { Select } from '@/Components/ui/select';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ batches: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'imports.index');

const modules = ['entities', 'accounts', 'account_contact_infos', 'account_addresses'];
const form = useForm({ module: 'entities', file: null, campaign_id: null });

const columns = [
    { id: 'module', accessorKey: 'module', header: 'Module', sortable: true },
    { id: 'filename', accessorKey: 'filename', header: 'File', sortable: true },
    { id: 'status', accessorKey: 'status', header: 'Status', sortable: true },
    { id: 'campaign', header: 'Campaign' },
    { id: 'importer', header: 'Imported by' },
    { id: 'created_at', accessorKey: 'created_at', header: 'Date', sortable: true },
];

function onFileChange(event) {
    form.file = event.target.files[0];
}

function submitUpload() {
    form.post(route('imports.store'), { forceFormData: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <Head title="Import" />
    <AppLayout>
        <template #header>Import</template>
        <ListingPage title="Import batches" :search="filters.search ?? ''" @search="onSearch" @clear="onClear">
            <template v-if="can.run" #filters>
                <form class="flex flex-wrap items-end gap-2 w-full mt-2 pt-2 border-t" style="border-color: var(--color-border)" @submit.prevent="submitUpload">
                    <Select v-model="form.module" :options="modules" class="w-48" placeholder="Module" />
                    <input type="file" accept=".csv,.txt" @change="onFileChange" />
                    <Button type="submit" size="sm" :disabled="form.processing">Upload CSV</Button>
                </form>
            </template>
            <CollectimateDataTable
                :value="batches.data"
                :columns="columns"
                :rows="batches.per_page"
                :total-records="batches.total"
                :first="(batches.current_page - 1) * batches.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.campaign="{ row }">{{ row.campaign?.name ?? '—' }}</template>
                <template #cell.importer="{ row }">{{ row.importer?.username }}</template>
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
