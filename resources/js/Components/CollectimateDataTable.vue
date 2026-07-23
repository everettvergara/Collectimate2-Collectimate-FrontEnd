<script setup>
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

defineProps({
    value: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    paginator: {
        type: Boolean,
        default: true,
    },
    rows: {
        type: Number,
        default: 15,
    },
    totalRecords: {
        type: Number,
        default: 0,
    },
    first: {
        type: Number,
        default: 0,
    },
    sortField: {
        type: String,
        default: null,
    },
    sortOrder: {
        type: Number,
        default: null,
    },
    lazy: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['page', 'sort']);
</script>

<template>
    <DataTable
        class="table-collectimate"
        :value="value"
        :loading="loading"
        :paginator="paginator"
        :rows="rows"
        :total-records="totalRecords"
        :first="first"
        :lazy="lazy"
        :sort-field="sortField"
        :sort-order="sortOrder"
        :striped-rows="false"
        removable-sort
        @page="emit('page', $event)"
        @sort="emit('sort', $event)"
    >
        <slot />
        <template #empty>
            <div class="py-6 text-center" style="color: var(--color-text-muted)">No records found.</div>
        </template>
    </DataTable>
</template>
