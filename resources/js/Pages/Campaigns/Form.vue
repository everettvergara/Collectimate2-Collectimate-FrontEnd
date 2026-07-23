<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({ campaign: Object, entities: Array, statuses: Array });
const form = useForm({
    entity_id: props.campaign?.entity_id ?? null,
    campaign_code: props.campaign?.campaign_code ?? '',
    name: props.campaign?.name ?? '',
    description: props.campaign?.description ?? '',
    status: props.campaign?.status ?? 'active',
});
const submit = () => {
    if (props.campaign) form.put(route('campaigns.update', props.campaign.id));
    else form.post(route('campaigns.store'));
};
</script>

<template>
    <Head :title="campaign ? 'Edit Campaign' : 'Create Campaign'" />
    <AppLayout>
        <template #header>{{ campaign ? 'Edit Campaign' : 'Create Campaign' }}</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div>
                <label class="form-label block mb-1">Entity</label>
                <Select v-model="form.entity_id" :options="entities" option-label="name" option-value="id" class="w-full" placeholder="Select entity" />
                <InputError :message="form.errors.entity_id" />
            </div>
            <div><label class="form-label block mb-1">Code</label><InputText v-model="form.campaign_code" class="w-full" /><InputError :message="form.errors.campaign_code" /></div>
            <div><label class="form-label block mb-1">Name</label><InputText v-model="form.name" class="w-full" /><InputError :message="form.errors.name" /></div>
            <div><label class="form-label block mb-1">Status</label><Select v-model="form.status" :options="statuses" class="w-full" /></div>
            <div><label class="form-label block mb-1">Description</label><Textarea v-model="form.description" rows="4" class="w-full" /></div>
            <div class="flex gap-2"><Button type="submit" label="Save" :loading="form.processing" /><Link :href="route('campaigns.index')"><Button type="button" label="Cancel" severity="secondary" /></Link></div>
        </form>
    </AppLayout>
</template>
