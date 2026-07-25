<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
});

const showClearModal = ref(false);
const showCreateModal = ref(false);

const clearForm = useForm({ confirmation: '' });
const createForm = useForm({});

const clearReady = computed(() => clearForm.confirmation.trim() === 'CLEAR');

function openClearModal() {
    clearForm.reset();
    clearForm.clearErrors();
    showClearModal.value = true;
}

function submitClear() {
    clearForm.post(route('demo-mode.clear'), {
        preserveScroll: true,
        onSuccess: () => {
            showClearModal.value = false;
            clearForm.reset();
        },
    });
}

function submitCreate() {
    createForm.post(route('demo-mode.create-demo'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
        },
    });
}
</script>

<template>
    <Head title="Demo Mode" />
    <AppLayout>
        <template #header>Demo Mode</template>

        <div class="max-w-2xl space-y-6">
            <div>
                <h1 class="page-title">Demo Mode</h1>
                <p class="mt-1 text-sm" style="color: var(--color-text-muted)">
                    Clear non-template CRM data or generate FYD / ABC demo entities from
                    Template - Collections.
                </p>
            </div>

            <section
                class="space-y-3 rounded border p-4"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <h2 class="text-sm font-semibold">Template</h2>
                <div v-if="summary.template" class="text-sm space-y-1">
                    <div>
                        <span style="color: var(--color-text-muted)">Entity:</span>
                        {{ summary.template.name }}
                        ({{ summary.template.entity_code }})
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted)">Statuses:</span>
                        {{ summary.template.status_count }}
                        ·
                        <span style="color: var(--color-text-muted)">Action codes:</span>
                        {{ summary.template.action_count }}
                    </div>
                </div>
                <p v-else class="text-sm" style="color: var(--color-text-muted)">
                    Template not found. Run EntitySeeder or use Clear / Create Demo to restore it.
                </p>
            </section>

            <section
                class="space-y-3 rounded border p-4"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <h2 class="text-sm font-semibold">Demo entities</h2>
                <div v-if="summary.demo_entities?.length" class="space-y-3">
                    <div
                        v-for="entity in summary.demo_entities"
                        :key="entity.id"
                        class="text-sm space-y-1 border-t pt-3 first:border-t-0 first:pt-0"
                        style="border-color: var(--color-border)"
                    >
                        <div class="font-medium">
                            {{ entity.name }}
                            <span style="color: var(--color-text-muted)">({{ entity.entity_code }})</span>
                        </div>
                        <div style="color: var(--color-text-muted)">
                            Campaigns: {{ entity.campaign_count }} · Common Pool accounts:
                            {{ entity.common_pool_accounts }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm" style="color: var(--color-text-muted)">
                    No demo entities yet. Create demo data to generate FYD Collections and ABC
                    Collections.
                </p>
            </section>

            <section class="flex flex-wrap gap-3">
                <Button type="button" variant="destructive" @click="openClearModal">
                    Clear non-template data
                </Button>
                <Button type="button" @click="showCreateModal = true">Create demo entities</Button>
            </section>
        </div>

        <Modal :show="showClearModal" max-width="md" @close="showClearModal = false">
            <div class="p-6 space-y-3">
                <h2 class="text-lg font-semibold">Clear non-template data</h2>
                <p class="text-sm" style="color: var(--color-text-muted)">
                    Permanently deletes all entities, campaigns, and accounts except
                    <strong>Template - Collections</strong>, and removes demo users
                    <strong>abc</strong> / <strong>fyd</strong> (and their agent profiles).
                    Type <strong>CLEAR</strong> to confirm.
                </p>
                <div>
                    <Input v-model="clearForm.confirmation" class="w-full" placeholder="CLEAR" />
                    <InputError :message="clearForm.errors.confirmation" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="showClearModal = false">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="!clearReady || clearForm.processing"
                        @click="submitClear"
                    >
                        Clear data
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal :show="showCreateModal" max-width="md" @close="showCreateModal = false">
            <div class="p-6 space-y-3">
                <h2 class="text-lg font-semibold">Create demo entities</h2>
                <p class="text-sm" style="color: var(--color-text-muted)">
                    Creates <strong>FYD Collections</strong> and <strong>ABC Collections</strong>,
                    copies statuses and action codes from the template, ensures 8 campaigns each,
                    and loads 1,000 Common Pool accounts per entity (skipped if Common Pool already
                    has accounts).
                </p>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="showCreateModal = false">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :disabled="createForm.processing"
                        @click="submitCreate"
                    >
                        Create demo
                    </Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
