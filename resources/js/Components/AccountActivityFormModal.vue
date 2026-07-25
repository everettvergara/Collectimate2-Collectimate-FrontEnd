<script setup>
import { computed, ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import {
    buildAccountTemplateTokens,
    resolveTemplateBody,
} from '@/Composables/useTemplateTokens';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'single' }, // 'single' | 'bulk'
    account: { type: Object, default: null },
    activityTypes: { type: Array, default: () => [] },
    entityStatuses: { type: Array, default: () => [] },
    entityActionCodes: { type: Array, default: () => [] },
    entityTemplates: { type: Array, default: () => [] },
    contactOptions: { type: Array, default: () => [] },
    addressOptions: { type: Array, default: () => [] },
    actorLabel: { type: String, default: '' },
    submitUrl: { type: String, required: true },
    extraPayload: { type: Object, default: () => ({}) },
    subtitle: { type: String, default: '' },
    remarksRequired: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'success']);

const attachmentInputRef = ref(null);
const selectedAttachmentNames = ref([]);

function pad2(n) {
    return String(n).padStart(2, '0');
}

function defaultOccurredAt() {
    const d = new Date();
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

function defaultDate() {
    const d = new Date();
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function defaultTime() {
    const d = new Date();
    return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

function agentLabel(profile, emptyLabel = '') {
    if (profile?.display_name) return profile.display_name;
    if (profile) {
        const name = [profile.first_name, profile.last_name].filter(Boolean).join(' ');
        if (name) return name;
    }
    return emptyLabel;
}

const form = useForm({
    occurred_at: defaultOccurredAt(),
    activity_type_id: null,
    entity_status_id: null,
    entity_action_code_id: null,
    entity_template_id: null,
    reference_amount: '',
    reference_date: '',
    reference_time: '',
    reference_text: '',
    reference_contact_info_id: null,
    reference_address_id: null,
    remarks: '',
    attachments: [],
});

const SEND_TEMPLATE_CHANNELS = {
    sms_send: 'sms',
    email_send: 'email',
    chat_send: 'chat',
};

const selectedActivityTypeCode = computed(() => {
    const type = (props.activityTypes ?? []).find((t) => t.id === form.activity_type_id);
    return type?.code ?? null;
});

const activityTemplateChannel = computed(
    () => SEND_TEMPLATE_CHANNELS[selectedActivityTypeCode.value] ?? null,
);

const showActivityTemplateSelect = computed(() => !!activityTemplateChannel.value);

const activityTemplateOptions = computed(() => {
    const channel = activityTemplateChannel.value;
    if (!channel) return [];
    return (props.entityTemplates ?? []).filter(
        (t) => Array.isArray(t.types) && t.types.includes(channel),
    );
});

const showContactAddress = computed(() => props.mode === 'single');

watch(
    () => form.activity_type_id,
    () => {
        if (!showActivityTemplateSelect.value) {
            form.entity_template_id = null;
            return;
        }
        const stillValid = activityTemplateOptions.value.some(
            (t) => t.id === form.entity_template_id,
        );
        if (!stillValid) {
            form.entity_template_id = null;
        }
    },
);

watch(
    () => form.entity_template_id,
    (templateId) => {
        if (!templateId) return;
        const template = (props.entityTemplates ?? []).find((t) => t.id === templateId);
        if (!template?.body) {
            form.reference_text = '';
            return;
        }
        if (props.mode === 'single' && props.account) {
            const tokens = buildAccountTemplateTokens(props.account, {
                assignedAgent: agentLabel(props.account?.assigned_agent_profile, ''),
            });
            form.reference_text = resolveTemplateBody(template.body, tokens);
            return;
        }
        // Bulk: show raw body; server resolves tokens per account on save.
        form.reference_text = String(template.body);
    },
);

function resetAttachments() {
    form.attachments = [];
    selectedAttachmentNames.value = [];
    if (attachmentInputRef.value) attachmentInputRef.value.value = '';
}

function prefill() {
    form.clearErrors();
    form.occurred_at = defaultOccurredAt();
    form.entity_template_id = null;
    form.reference_contact_info_id = null;
    form.reference_address_id = null;
    form.remarks = '';
    resetAttachments();

    if (props.mode === 'single' && props.account) {
        form.activity_type_id = props.account?.last_activity_type_id ?? null;
        form.entity_status_id = props.account?.entity_status_id ?? null;
        form.entity_action_code_id = props.account?.entity_action_code_id ?? null;
        form.reference_amount = props.account?.last_reference_amount ?? '';
        form.reference_date = defaultDate();
        form.reference_time = defaultTime();
        form.reference_text = props.account?.last_reference_text ?? '';
        return;
    }

    form.activity_type_id = null;
    form.entity_status_id = null;
    form.entity_action_code_id = null;
    form.reference_amount = '';
    form.reference_date = defaultDate();
    form.reference_time = defaultTime();
    form.reference_text = '';
}

watch(
    () => props.show,
    (open) => {
        if (open) {
            prefill();
        }
    },
);

function onAttachmentsChange(event) {
    const files = Array.from(event.target.files ?? []);
    form.attachments = files;
    selectedAttachmentNames.value = files.map((f) => f.name);
}

function close() {
    emit('close');
    form.reset();
    form.clearErrors();
    form.occurred_at = defaultOccurredAt();
    resetAttachments();
}

function submit() {
    form
        .transform((data) => {
            const payload = {
                ...data,
                ...props.extraPayload,
            };
            if (!showContactAddress.value) {
                delete payload.reference_contact_info_id;
                delete payload.reference_address_id;
            }
            return payload;
        })
        .post(props.submitUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                emit('success');
                emit('close');
            },
        });
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <form class="p-6 space-y-4" @submit.prevent="submit">
            <div>
                <h2 class="text-lg font-semibold">Add activity</h2>
                <p v-if="subtitle" class="text-sm mt-1" style="color: var(--color-text-muted)">
                    {{ subtitle }}
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="form-label block mb-1">Status</label>
                    <Select
                        v-model="form.entity_status_id"
                        :options="entityStatuses"
                        option-label="name"
                        option-value="id"
                        class="w-full"
                    />
                    <InputError :message="form.errors.entity_status_id" />
                </div>
                <div>
                    <label class="form-label block mb-1">Action</label>
                    <Select
                        v-model="form.entity_action_code_id"
                        :options="entityActionCodes"
                        option-label="name"
                        option-value="id"
                        class="w-full"
                        show-clear
                    />
                </div>
                <div>
                    <label class="form-label block mb-1">Activity type</label>
                    <Select
                        v-model="form.activity_type_id"
                        :options="activityTypes"
                        option-label="name"
                        option-value="id"
                        class="w-full"
                    />
                    <InputError :message="form.errors.activity_type_id" />
                </div>
                <div v-if="showActivityTemplateSelect">
                    <label class="form-label block mb-1">Template</label>
                    <Select
                        v-model="form.entity_template_id"
                        :options="activityTemplateOptions"
                        option-label="slug"
                        option-value="id"
                        class="w-full"
                        show-clear
                        placeholder="Select template…"
                    />
                    <InputError :message="form.errors.entity_template_id" />
                    <p
                        v-if="mode === 'bulk'"
                        class="text-xs mt-1"
                        style="color: var(--color-text-muted)"
                    >
                        Tokens are resolved per account on save.
                    </p>
                </div>
                <div>
                    <label class="form-label block mb-1">Datetime</label>
                    <Input v-model="form.occurred_at" type="datetime-local" class="w-full" />
                    <InputError :message="form.errors.occurred_at" />
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label block mb-1">Agent</label>
                    <div class="text-sm py-2">{{ actorLabel || 'System' }}</div>
                </div>
                <div>
                    <label class="form-label block mb-1">Ref amount</label>
                    <Input v-model="form.reference_amount" type="number" step="0.01" class="w-full" />
                </div>
                <div>
                    <label class="form-label block mb-1">Ref date</label>
                    <Input v-model="form.reference_date" type="date" class="w-full" />
                </div>
                <div>
                    <label class="form-label block mb-1">Ref time</label>
                    <Input v-model="form.reference_time" type="time" class="w-full" />
                </div>
                <div v-if="showContactAddress">
                    <label class="form-label block mb-1">Reference contact</label>
                    <Select
                        v-model="form.reference_contact_info_id"
                        :options="contactOptions"
                        option-label="label"
                        option-value="id"
                        class="w-full"
                        show-clear
                        placeholder="Select contact…"
                    />
                    <InputError :message="form.errors.reference_contact_info_id" />
                </div>
                <div v-if="showContactAddress">
                    <label class="form-label block mb-1">Reference address</label>
                    <Select
                        v-model="form.reference_address_id"
                        :options="addressOptions"
                        option-label="label"
                        option-value="id"
                        class="w-full"
                        show-clear
                        placeholder="Select address…"
                    />
                    <InputError :message="form.errors.reference_address_id" />
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label block mb-1">Ref text</label>
                    <Textarea v-model="form.reference_text" rows="5" class="w-full" />
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label block mb-1">
                        Remarks
                        <span v-if="remarksRequired" class="text-red-600">*</span>
                    </label>
                    <Textarea v-model="form.remarks" rows="5" class="w-full" />
                    <InputError :message="form.errors.remarks || form.errors.bulk" />
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label block mb-1">Attachments</label>
                    <input
                        ref="attachmentInputRef"
                        type="file"
                        multiple
                        accept="image/*,.pdf,application/pdf"
                        class="block w-full text-sm"
                        @change="onAttachmentsChange"
                    />
                    <p class="text-xs mt-1" style="color: var(--color-text-muted)">
                        Images and PDF only. Up to 10 files, 5 MB each.
                    </p>
                    <ul v-if="selectedAttachmentNames.length" class="mt-2 text-sm space-y-0.5">
                        <li v-for="name in selectedAttachmentNames" :key="name">{{ name }}</li>
                    </ul>
                    <InputError :message="form.errors.attachments || form.errors['attachments.0']" />
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="secondary" size="sm" @click="close">Cancel</Button>
                <Button type="submit" size="sm" :disabled="form.processing">Save</Button>
            </div>
        </form>
    </Modal>
</template>
