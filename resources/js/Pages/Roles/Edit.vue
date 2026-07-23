<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';

const props = defineProps({
    role: Object,
    permissions: Object,
    assigned: Array,
});

const form = useForm({
    permission_ids: [...props.assigned],
});

function toggle(id, checked) {
    if (checked) {
        if (!form.permission_ids.includes(id)) form.permission_ids.push(id);
    } else {
        form.permission_ids = form.permission_ids.filter((p) => p !== id);
    }
}

const submit = () => form.put(route('roles.update', props.role.id));
</script>

<template>
    <Head :title="`Edit ${role.name}`" />
    <AppLayout>
        <template #header>{{ role.name }}</template>

        <form class="space-y-4" @submit.prevent="submit">
            <h1 class="page-title">Permissions</h1>

            <div
                v-for="(items, module) in permissions"
                :key="module"
                class="p-4 border rounded"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div class="form-label mb-3">{{ module }}</div>
                <div class="grid md:grid-cols-2 gap-2">
                    <label v-for="perm in items" :key="perm.id" class="flex items-center gap-2">
                        <Checkbox
                            :model-value="form.permission_ids.includes(perm.id)"
                            binary
                            @update:model-value="toggle(perm.id, $event)"
                        />
                        <span>{{ perm.name }}</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-2">
                <Button type="submit" label="Save" :loading="form.processing" />
                <Link :href="route('roles.index')">
                    <Button type="button" label="Cancel" severity="secondary" />
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
