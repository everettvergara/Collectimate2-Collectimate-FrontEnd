<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';

const props = defineProps({ campaign: Object, availableAgents: Array, can: Object });

const assignForm = useForm({ agent_profile_id: null });

function assignAgent() {
    assignForm.post(route('campaigns.assignments.store', props.campaign.id), {
        onSuccess: () => assignForm.reset(),
    });
}

function removeAgent(agentProfileId) {
    router.delete(route('campaigns.assignments.destroy', [props.campaign.id, agentProfileId]));
}

function archive() {
    router.post(route('campaigns.archive', props.campaign.id));
}
</script>

<template>
    <Head :title="campaign.name" />
    <AppLayout>
        <template #header>{{ campaign.name }}</template>
        <div class="space-y-4 max-w-3xl">
            <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="grid grid-cols-2 gap-3">
                    <div><div class="form-label">Code</div><div>{{ campaign.campaign_code }}</div></div>
                    <div><div class="form-label">Status</div><div>{{ campaign.status }}</div></div>
                    <div>
                        <div class="form-label">Entity</div>
                        <div>
                            <Link v-if="campaign.entity" :href="route('entities.show', campaign.entity.id)" class="hover:underline" style="color: var(--color-primary)">{{ campaign.entity.name }}</Link>
                            <span v-else>—</span>
                        </div>
                    </div>
                    <div><div class="form-label">Accounts</div><div>{{ campaign.accounts_count }}</div></div>
                </div>
                <p v-if="campaign.description" class="mt-3" style="color: var(--color-text-muted)">{{ campaign.description }}</p>
                <div class="flex gap-2 mt-4">
                    <Link v-if="can.update" :href="route('campaigns.edit', campaign.id)"><Button label="Edit" size="small" /></Link>
                    <Button v-if="can.archive && campaign.status !== 'archived'" label="Archive" severity="secondary" size="small" @click="archive" />
                </div>
            </div>

            <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-2">Accounts</div>
                <ul v-if="campaign.accounts?.length" class="space-y-1">
                    <li v-for="a in campaign.accounts" :key="a.id">
                        <Link :href="route('accounts.show', a.id)" class="hover:underline" style="color: var(--color-primary)">{{ a.account_number }}</Link>
                    </li>
                </ul>
                <p v-else style="color: var(--color-text-muted)">No accounts yet.</p>
            </div>

            <div v-if="can.manageAssignments" class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-3">Agent assignments</div>
                <div class="flex gap-2 mb-4">
                    <Select v-model="assignForm.agent_profile_id" :options="availableAgents" option-label="display_name" option-value="id" placeholder="Select agent" class="flex-1" />
                    <Button label="Assign" size="small" :loading="assignForm.processing" @click="assignAgent" />
                </div>
                <ul class="space-y-2">
                    <li v-for="agent in campaign.agent_profiles" :key="agent.id" class="flex justify-between items-center">
                        <span>{{ agent.display_name }} ({{ agent.employee_number }})</span>
                        <Button label="Remove" severity="danger" size="small" text @click="removeAgent(agent.id)" />
                    </li>
                    <li v-if="!campaign.agent_profiles?.length" style="color: var(--color-text-muted)">No agents assigned.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
