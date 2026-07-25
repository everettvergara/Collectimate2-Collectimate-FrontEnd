<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Select } from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ campaign: Object, availableAgents: Array, can: Object });

const showAssignModal = ref(false);
const assignForm = useForm({ agent_profile_id: null });

function openAssignModal() {
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.agent_profile_id = null;
    showAssignModal.value = true;
}

function closeAssignModal() {
    showAssignModal.value = false;
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.agent_profile_id = null;
}

function assignAgent() {
    assignForm.post(route('campaigns.assignments.store', props.campaign.id), {
        preserveScroll: true,
        onSuccess: () => closeAssignModal(),
    });
}

function removeAgent(agentProfileId) {
    if (confirm('Remove this agent from the campaign?')) {
        router.delete(route('campaigns.assignments.destroy', [props.campaign.id, agentProfileId]));
    }
}

function archive() {
    router.post(route('campaigns.archive', props.campaign.id));
}

function destroyCampaign() {
    if (confirm('Delete this campaign and all of its accounts? This cannot be undone.')) {
        router.delete(route('campaigns.destroy', props.campaign.id));
    }
}
</script>

<template>
    <Head :title="campaign.name" />
    <AppLayout>
        <template #header>{{ campaign.name }}</template>
        <div class="space-y-4 max-w-4xl">
            <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="grid grid-cols-2 gap-3">
                    <div><div class="form-label">Code</div><div>{{ campaign.campaign_code }}</div></div>
                    <div><div class="form-label">Status</div><div>{{ campaign.status }}</div></div>
                    <div>
                        <div class="form-label">Entity</div>
                        <div>
                            <Link
                                v-if="campaign.entity"
                                :href="route('entities.show', campaign.entity.id)"
                                class="hover:underline"
                                style="color: var(--color-primary)"
                            >
                                {{ campaign.entity.name }}
                            </Link>
                            <span v-else>—</span>
                        </div>
                    </div>
                    <div>
                        <div class="form-label">Agents</div>
                        <div>{{ campaign.agent_profiles_count ?? campaign.agent_profiles?.length ?? 0 }}</div>
                    </div>
                </div>
                <p v-if="campaign.description" class="mt-3" style="color: var(--color-text-muted)">{{ campaign.description }}</p>
                <div class="flex gap-2 mt-4">
                    <Link v-if="can.update" :href="route('campaigns.edit', campaign.id)">
                        <Button size="sm">Edit</Button>
                    </Link>
                    <Button v-if="can.archive && campaign.status !== 'archived'" variant="secondary" size="sm" @click="archive">
                        Archive
                    </Button>
                    <Button v-if="can.delete" variant="destructive" size="sm" @click="destroyCampaign">
                        Delete
                    </Button>
                </div>
            </div>

            <div class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="form-label mb-0">Agents</div>
                    <Button v-if="can.manageAssignments" size="sm" @click="openAssignModal">
                        Assign
                    </Button>
                </div>
                <Table v-if="campaign.agent_profiles?.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Display name</TableHead>
                            <TableHead>Employee #</TableHead>
                            <TableHead>Position</TableHead>
                            <TableHead v-if="can.manageAssignments" class="w-[80px]">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="agent in campaign.agent_profiles" :key="agent.id">
                            <TableCell>{{ agent.display_name }}</TableCell>
                            <TableCell>{{ agent.employee_number }}</TableCell>
                            <TableCell>{{ agent.position || '—' }}</TableCell>
                            <TableCell v-if="can.manageAssignments">
                                <ListingRowActions :on-delete="() => removeAgent(agent.id)" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else style="color: var(--color-text-muted)">No agents assigned.</p>
            </div>
        </div>

        <Modal :show="showAssignModal" max-width="md" @close="closeAssignModal">
            <form class="p-6 space-y-4" @submit.prevent="assignAgent">
                <h2 class="text-lg font-semibold">Assign agent</h2>
                <div v-if="availableAgents?.length">
                    <label class="form-label block mb-1">Agent</label>
                    <Select
                        v-model="assignForm.agent_profile_id"
                        :options="availableAgents"
                        option-label="display_name"
                        option-value="id"
                        placeholder="Select agent"
                        class="w-full"
                    />
                    <InputError :message="assignForm.errors.agent_profile_id" />
                </div>
                <p v-else class="text-sm" style="color: var(--color-text-muted)">
                    No available agents to assign.
                </p>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeAssignModal">Cancel</Button>
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="assignForm.processing || !assignForm.agent_profile_id"
                    >
                        Assign
                    </Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
