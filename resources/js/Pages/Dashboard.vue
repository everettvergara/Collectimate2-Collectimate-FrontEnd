<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            campaigns: 0,
            entities: 0,
            accounts: 0,
            users: 0,
        }),
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>Dashboard</template>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div
                v-for="(value, key) in stats"
                :key="key"
                class="p-4 border rounded"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div class="form-label mb-1">{{ key }}</div>
                <div class="page-title">{{ value }}</div>
            </div>
        </div>

        <div
            class="p-4 border rounded"
            style="background: var(--color-bg-surface); border-color: var(--color-border)"
        >
            <div class="form-label mb-2">Recent activity</div>
            <p style="color: var(--color-text-muted)">No recent activity yet.</p>
        </div>
    </AppLayout>
</template>
