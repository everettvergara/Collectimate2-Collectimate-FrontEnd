<script setup>
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    search: {
        type: String,
        default: '',
    },
    canExport: {
        type: Boolean,
        default: false,
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
    createHref: {
        type: String,
        default: null,
    },
    exportHref: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['search', 'clear', 'export']);

const localSearch = ref(props.search);

watch(
    () => props.search,
    (value) => {
        localSearch.value = value;
    },
);

function applySearch() {
    emit('search', localSearch.value);
}

function clearFilters() {
    localSearch.value = '';
    emit('clear');
}

function onExport() {
    if (props.exportHref) {
        window.location.href = props.exportHref;
    } else {
        emit('export');
    }
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">{{ title }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="canExport"
                    label="Export"
                    severity="secondary"
                    size="small"
                    @click="onExport"
                />
                <slot name="actions" />
                <Button
                    v-if="canCreate && createHref"
                    label="Create"
                    size="small"
                    as="a"
                    :href="createHref"
                />
            </div>
        </div>

        <div
            class="flex flex-wrap items-end gap-3 p-3 border rounded"
            style="background: var(--color-bg-subtle); border-color: var(--color-border)"
        >
            <div class="flex-1 min-w-[12rem]">
                <label for="listing-search" class="form-label block mb-1">Search</label>
                <InputText
                    id="listing-search"
                    v-model="localSearch"
                    class="w-full"
                    placeholder="Search..."
                    @keyup.enter="applySearch"
                />
            </div>
            <Button label="Search" size="small" @click="applySearch" />
            <Button label="Clear" severity="secondary" size="small" @click="clearFilters" />
            <slot name="filters" />
        </div>

        <slot />
    </div>
</template>
