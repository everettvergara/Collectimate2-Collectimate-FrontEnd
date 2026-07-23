<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';

const props = defineProps({ account: Object, campaigns: Array, statuses: Array });
const form = useForm({
    campaign_id: props.account?.campaign_id ?? null,
    account_number: props.account?.account_number ?? '',
    product: props.account?.product ?? '',
    balance: props.account?.balance ?? '',
    due_date: props.account?.due_date ?? '',
    external_reference: props.account?.external_reference ?? '',
    status_id: props.account?.status_id ?? null,
    notes: props.account?.notes ?? '',
});

const selectedCampaign = computed(() => props.campaigns?.find((c) => c.id === form.campaign_id) ?? props.account?.campaign ?? null);

const submit = () => {
    if (props.account) form.put(route('accounts.update', props.account.id));
    else form.post(route('accounts.store'));
};
</script>

<template>
    <Head :title="account ? 'Edit Account' : 'Create Account'" />
    <AppLayout>
        <template #header>{{ account ? 'Edit Account' : 'Create Account' }}</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div><label class="form-label block mb-1">Campaign</label><Select v-model="form.campaign_id" :options="campaigns" option-label="name" option-value="id" class="w-full" /><InputError :message="form.errors.campaign_id" /></div>
            <div>
                <label class="form-label block mb-1">Entity</label>
                <div class="py-2">{{ selectedCampaign?.entity?.name ?? '—' }}</div>
            </div>
            <div><label class="form-label block mb-1">Account number</label><InputText v-model="form.account_number" class="w-full" /><InputError :message="form.errors.account_number" /></div>
            <div><label class="form-label block mb-1">Product</label><InputText v-model="form.product" class="w-full" /></div>
            <div><label class="form-label block mb-1">Balance</label><InputText v-model="form.balance" type="number" step="0.01" class="w-full" /></div>
            <div><label class="form-label block mb-1">Due date</label><InputText v-model="form.due_date" type="date" class="w-full" /></div>
            <div><label class="form-label block mb-1">External reference</label><InputText v-model="form.external_reference" class="w-full" /></div>
            <div><label class="form-label block mb-1">Status</label><Select v-model="form.status_id" :options="statuses" option-label="name" option-value="id" class="w-full" show-clear /></div>
            <div><label class="form-label block mb-1">Notes</label><Textarea v-model="form.notes" rows="3" class="w-full" /></div>
            <div class="flex gap-2"><Button type="submit" label="Save" :loading="form.processing" /><Link :href="route('accounts.index')"><Button type="button" label="Cancel" severity="secondary" /></Link></div>
        </form>
    </AppLayout>
</template>
