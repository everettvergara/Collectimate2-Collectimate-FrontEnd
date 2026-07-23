<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ summaries: Object });
</script>

<template>
    <Head title="Reports" />
    <AppLayout>
        <template #header>Reports</template>
        <div class="space-y-4">
            <h1 class="page-title">Summary reports</h1>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div v-for="(value, key) in summaries" :key="key" class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="form-label mb-1">{{ key.replace('_', ' ') }}</div>
                    <div class="page-title">{{ value }}</div>
                </div>
            </div>
            <p style="color: var(--color-text-muted)">Detailed export reports will be expanded in a later slice.</p>
        </div>
    </AppLayout>
</template>
