<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({ entity: Object, can: Object });
</script>

<template>
    <Head :title="entity.name" />
    <AppLayout>
        <template #header>{{ entity.name }}</template>
        <div class="space-y-4 max-w-3xl">
            <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="grid grid-cols-2 gap-3">
                    <div><div class="form-label">Code</div><div>{{ entity.entity_code }}</div></div>
                    <div><div class="form-label">Status</div><div>{{ entity.status?.name ?? '—' }}</div></div>
                    <div><div class="form-label">Birthdate</div><div>{{ entity.birthdate ?? '—' }}</div></div>
                </div>
                <Link v-if="can.update" :href="route('entities.edit', entity.id)" class="inline-block mt-4"><Button label="Edit" size="small" /></Link>
            </div>
            <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-2">Campaigns ({{ entity.campaigns?.length ?? 0 }})</div>
                <ul v-if="entity.campaigns?.length" class="space-y-1">
                    <li v-for="c in entity.campaigns" :key="c.id">
                        <Link :href="route('campaigns.show', c.id)" class="hover:underline" style="color: var(--color-primary)">{{ c.name }} ({{ c.campaign_code }})</Link>
                    </li>
                </ul>
                <p v-else style="color: var(--color-text-muted)">No campaigns yet.</p>
            </div>
        </div>
    </AppLayout>
</template>
