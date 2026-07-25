<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ settings: Array });
const form = useForm({
    settings: props.settings.map((s) => ({ id: s.id, value: s.value ?? '' })),
});
const submit = () => form.put(route('settings.update'));
</script>

<template>
    <Head title="Settings" />
    <AppLayout>
        <template #header>Settings</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <h1 class="page-title">Application settings</h1>
            <div v-if="!settings.length" style="color: var(--color-text-muted)">No settings configured yet.</div>
            <div v-for="(setting, index) in settings" :key="setting.id" class="p-3 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <label class="form-label block mb-1">{{ setting.key }}</label>
                <Input v-model="form.settings[index].value" class="w-full" />
                <div class="form-label mt-1">{{ setting.group }}</div>
            </div>
            <Button v-if="settings.length" type="submit" :disabled="form.processing">Save</Button>
        </form>
    </AppLayout>
</template>
