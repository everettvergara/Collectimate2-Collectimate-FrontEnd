<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';

const props = defineProps({ status: Object });
const form = useForm({
    name: props.status?.name ?? '',
    slug: props.status?.slug ?? '',
    category: props.status?.category ?? 'general',
    color: props.status?.color ?? '',
    sort_order: props.status?.sort_order ?? 0,
    is_active: props.status?.is_active ?? true,
});
const submit = () => {
    if (props.status) form.put(route('statuses.update', props.status.id));
    else form.post(route('statuses.store'));
};
</script>

<template>
    <Head :title="status ? 'Edit Status' : 'Create Status'" />
    <AppLayout>
        <template #header>{{ status ? 'Edit Status' : 'Create Status' }}</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div><label class="form-label block mb-1">Name</label><InputText v-model="form.name" class="w-full" /><InputError :message="form.errors.name" /></div>
            <div><label class="form-label block mb-1">Slug</label><InputText v-model="form.slug" class="w-full" /><InputError :message="form.errors.slug" /></div>
            <div><label class="form-label block mb-1">Category</label><InputText v-model="form.category" class="w-full" /></div>
            <div><label class="form-label block mb-1">Color</label><InputText v-model="form.color" class="w-full" placeholder="#2F5D8C" /></div>
            <div><label class="form-label block mb-1">Sort order</label><InputText v-model="form.sort_order" type="number" class="w-full" /></div>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_active" binary /><span>Active</span></label>
            <div class="flex gap-2"><Button type="submit" label="Save" :loading="form.processing" /><Link :href="route('statuses.index')"><Button type="button" label="Cancel" severity="secondary" /></Link></div>
        </form>
    </AppLayout>
</template>
