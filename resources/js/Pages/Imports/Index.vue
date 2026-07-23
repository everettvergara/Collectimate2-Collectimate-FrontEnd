<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, useForm } from '@inertiajs/vue3';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Select from 'primevue/select';

const props = defineProps({ batches: Object, filters: Object, can: Object });
const { onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'imports.index');

const modules = ['entities', 'accounts', 'account_contact_infos', 'account_addresses'];
const form = useForm({ module: 'entities', file: null, campaign_id: null });

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
                    <Select v-model="form.module" :options="modules" class="w-48" />
                    <input type="file" accept=".csv,.txt" @change="onFileChange" />
                    <Button type="submit" label="Upload CSV" size="small" :loading="form.processing" />
                </form>
            </template>
            <CollectimateDataTable :value="batches.data" :rows="batches.per_page" :total-records="batches.total" :first="(batches.current_page - 1) * batches.per_page" :sort-field="filters.sort" :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null" @page="onPage" @sort="onSort">
                <Column field="module" header="Module" sortable />
                <Column field="filename" header="File" sortable />
                <Column field="status" header="Status" sortable />
                <Column header="Campaign"><template #body="{ data }">{{ data.campaign?.name ?? '—' }}</template></Column>
                <Column header="Imported by"><template #body="{ data }">{{ data.importer?.username }}</template></Column>
                <Column field="created_at" header="Date" sortable />
            </CollectimateDataTable>
        </ListingPage>
    </AppLayout>
</template>
