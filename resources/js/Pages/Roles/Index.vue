<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({
    roles: Array,
});
</script>

<template>
    <Head title="Roles & Permissions" />
    <AppLayout>
        <template #header>Roles & Permissions</template>

        <div class="space-y-3">
            <h1 class="page-title">Roles</h1>
            <div
                v-for="role in roles"
                :key="role.id"
                class="flex items-center justify-between p-3 border rounded"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div>
                    <div class="page-title">{{ role.name }}</div>
                    <div style="color: var(--color-text-muted)">{{ role.description }}</div>
                    <div class="form-label mt-1">{{ role.users_count }} users</div>
                </div>
                <Link :href="route('roles.edit', role.id)">
                    <Button label="Edit permissions" size="small" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
