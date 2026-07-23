<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';

const props = defineProps({ entity: Object, statuses: Array });
const form = useForm({
    entity_code: props.entity?.entity_code ?? '',
    name: props.entity?.name ?? '',
    birthdate: props.entity?.birthdate ?? '',
    status_id: props.entity?.status_id ?? null,
});
const submit = () => {
    if (props.entity) form.put(route('entities.update', props.entity.id));
    else form.post(route('entities.store'));
};
</script>

<template>
    <Head :title="entity ? 'Edit Entity' : 'Create Entity'" />
    <AppLayout>
        <template #header>{{ entity ? 'Edit Entity' : 'Create Entity' }}</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div><label class="form-label block mb-1">Entity code</label><InputText v-model="form.entity_code" class="w-full" /><InputError :message="form.errors.entity_code" /></div>
            <div><label class="form-label block mb-1">Name</label><InputText v-model="form.name" class="w-full" /><InputError :message="form.errors.name" /></div>
            <div><label class="form-label block mb-1">Birthdate</label><InputText v-model="form.birthdate" type="date" class="w-full" /></div>
            <div><label class="form-label block mb-1">Status</label><Select v-model="form.status_id" :options="statuses" option-label="name" option-value="id" class="w-full" show-clear placeholder="Optional" /></div>
            <div class="flex gap-2"><Button type="submit" label="Save" :loading="form.processing" /><Link :href="route('entities.index')"><Button type="button" label="Cancel" severity="secondary" /></Link></div>
        </form>
    </AppLayout>
</template>
