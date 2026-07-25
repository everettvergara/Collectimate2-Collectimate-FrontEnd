<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ profile: Object, can: Object });
</script>

<template>
    <Head :title="profile.display_name" />
    <AppLayout>
        <template #header>{{ profile.display_name }}</template>
        <div class="max-w-2xl space-y-4">
            <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="grid grid-cols-2 gap-3">
                    <div><div class="form-label">Employee #</div><div>{{ profile.employee_number }}</div></div>
                    <div><div class="form-label">Status</div><div>{{ profile.status }}</div></div>
                    <div><div class="form-label">Email</div><div>{{ profile.email }}</div></div>
                    <div><div class="form-label">Department</div><div>{{ profile.department }}</div></div>
                    <div><div class="form-label">User</div><div>{{ profile.user?.username ?? '—' }}</div></div>
                </div>
            </div>
            <div v-if="profile.campaigns?.length" class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-2">Campaigns</div>
                <ul class="list-disc ps-5"><li v-for="c in profile.campaigns" :key="c.id">{{ c.name }}</li></ul>
            </div>
            <Link v-if="can.update" :href="route('agent-profiles.edit', profile.id)">
                <Button size="sm">Edit</Button>
            </Link>
        </div>
    </AppLayout>
</template>
