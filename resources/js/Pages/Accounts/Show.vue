<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import InputError from '@/Components/InputError.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import AccountActivityFormModal from '@/Components/AccountActivityFormModal.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import {
    Activity,
    ArrowRight,
    Banknote,
    Braces,
    CalendarClock,
    Contact,
    FileImage,
    FileText,
    MapPin,
    MessageSquare,
    StickyNote,
    TextQuote,
    UserRound,
} from '@lucide/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    account: Object,
    activities: Object,
    accountStats: Object,
    statusExtras: Object,
    contactTypes: Array,
    addressTypes: Array,
    activityTypes: Array,
    entityStatuses: Array,
    entityActionCodes: Array,
    entityTemplates: Array,
    actorLabel: String,
    can: Object,
});

const fieldsEditing = ref(false);
const statusEditing = ref(false);
const showContactModal = ref(false);
const showAddressModal = ref(false);
const showActivityModal = ref(false);
const showDeleteActivityModal = ref(false);
const pendingDeleteActivityId = ref(null);
const deleteActivityConfirmText = ref('');
const deleteActivityProcessing = ref(false);
const DELETE_ACTIVITY_PHRASE = 'DELETEACTIVITY';
const fileInputRef = ref(null);

const canConfirmDeleteActivity = computed(
    () => deleteActivityConfirmText.value === DELETE_ACTIVITY_PHRASE,
);

function jsonFromAccount() {
    const fields = props.account?.custom_fields;
    if (fields && typeof fields === 'object' && Object.keys(fields).length) {
        return JSON.stringify(fields, null, 2);
    }
    return '{\n  \n}';
}

const jsonText = ref(jsonFromAccount());
const jsonClientError = ref('');
const fieldsForm = useForm({ custom_fields: jsonText.value });

const contactForm = useForm({
    type: 'email',
    name: '',
    relationship: '',
    value: '',
    is_primary: true,
});

function defaultAddressTypeCode() {
    const types = props.addressTypes ?? [];
    const def = types.find((t) => t.is_default);
    return def?.code ?? types[0]?.code ?? 'home';
}

const addressForm = useForm({
    type: defaultAddressTypeCode(),
    name: '',
    relationship: '',
    line1: '',
    line2: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    remarks: '',
    is_primary: true,
});

function timeFromAccount() {
    const t = props.account?.last_reference_time;
    if (!t) return '';
    return String(t).slice(0, 5);
}

const statusForm = useForm({
    entity_status_id: props.account?.entity_status_id ?? null,
    entity_action_code_id: props.account?.entity_action_code_id ?? null,
    last_reference_amount: props.account?.last_reference_amount ?? '',
    last_reference_date: props.account?.last_reference_date ?? '',
    last_reference_time: timeFromAccount(),
    last_reference_text: props.account?.last_reference_text ?? '',
    remarks: '',
});

const isNonPrimaryContact = computed(() => !contactForm.is_primary);
const isNonPrimaryAddress = computed(() => !addressForm.is_primary);

const contactSelectOptions = computed(() =>
    (props.account?.contact_infos ?? []).map((c) => ({
        id: c.id,
        label: [typeLabel(c.type), c.name, c.value].filter(Boolean).join(' · '),
    })),
);

const addressSelectOptions = computed(() =>
    (props.account?.addresses ?? []).map((a) => ({
        id: a.id,
        label: [addressTypeLabel(a.type), a.line1, a.city].filter(Boolean).join(' · '),
    })),
);

const activityCountGroups = computed(() => {
    const counts = props.accountStats?.activity_counts ?? {};
    const n = (code) => counts[code] ?? 0;

    const smsTotal = n('sms_send') + n('sms_receive');
    const callsTotal =
        n('manual_call_success') +
        n('manual_call_failed') +
        n('robo_call_success') +
        n('robo_call_failed');

    return [
        {
            label: 'General',
            rows: [
                { label: 'System', count: n('system') },
                { label: 'Others', count: n('others') },
            ],
        },
        {
            label: 'SMS',
            rows: [
                { label: 'SMS Send', count: n('sms_send') },
                { label: 'SMS Receive', count: n('sms_receive') },
                { label: 'Total', count: smsTotal, emphasize: true },
            ],
        },
        {
            label: 'Calls',
            rows: [
                { label: 'Manual Call Success', count: n('manual_call_success') },
                { label: 'Manual Call Failed', count: n('manual_call_failed') },
                { label: 'Robo Call Success', count: n('robo_call_success') },
                { label: 'Robo Call Failed', count: n('robo_call_failed') },
                { label: 'Total', count: callsTotal, emphasize: true },
            ],
        },
        {
            label: 'Online',
            rows: [
                { label: 'Email Send', count: n('email_send') },
                { label: 'Email Receive', count: n('email_receive') },
                { label: 'Chat Send', count: n('chat_send') },
                { label: 'Chat Receive', count: n('chat_receive') },
                { label: 'Skip', count: n('skip') },
            ],
        },
        {
            label: 'Onsite',
            rows: [{ label: 'Field', count: n('field') }],
        },
        {
            label: 'Total',
            rows: [
                { label: 'Overall', count: props.accountStats?.activity_total ?? 0, emphasize: true },
                {
                    label: 'Excl. System',
                    count: props.accountStats?.activity_total_excluding_system ?? 0,
                    emphasize: true,
                },
                { label: 'Positive', count: props.account?.positive_activity_count ?? 0, emphasize: true },
                { label: 'Negative', count: props.account?.negative_activity_count ?? 0, emphasize: true },
                { label: 'Neutral', count: props.account?.neutral_activity_count ?? 0, emphasize: true },
            ],
        },
    ];
});

function isImageAttachment(file) {
    const mime = String(file?.mime ?? '');
    return mime.startsWith('image/');
}

const valuePlaceholder = computed(() => {
    switch (contactForm.type) {
        case 'email':
            return 'Email';
        case 'mobile':
            return 'Mobile number';
        case 'facebook':
        case 'linkedin':
        case 'x':
        case 'instagram':
        case 'website':
            return 'Profile URL';
        default:
            return 'Value';
    }
});

const customFieldEntries = computed(() => {
    const fields = props.account?.custom_fields;
    if (!fields || typeof fields !== 'object') return [];
    return Object.entries(fields).map(([key, value]) => ({
        key,
        label: humanizeKey(key),
        value: formatFieldValue(value),
    }));
});

const contactColumns = [
    { id: 'type', header: 'Type' },
    { id: 'name', header: 'Name' },
    { id: 'relationship', header: 'Relationship' },
    { id: 'value', header: 'Value' },
    { id: 'success', header: 'Success' },
    { id: 'failed', header: 'Failed' },
    { id: 'total', header: 'Total' },
    { id: 'primary', header: 'Primary' },
    { id: 'actions', header: 'Actions' },
];

const addressColumns = [
    { id: 'type', header: 'Type' },
    { id: 'name', header: 'Name' },
    { id: 'relationship', header: 'Relationship' },
    { id: 'line1', header: 'Line 1' },
    { id: 'line2', header: 'Line 2' },
    { id: 'city', header: 'City' },
    { id: 'state', header: 'State' },
    { id: 'postal', header: 'Postal' },
    { id: 'country', header: 'Country' },
    { id: 'remarks', header: 'Remarks' },
    { id: 'primary', header: 'Primary' },
    { id: 'actions', header: 'Actions' },
];

const contactRows = computed(() => props.account?.contact_infos ?? []);
const addressRows = computed(() => props.account?.addresses ?? []);
const activityRows = computed(() => props.activities?.data ?? []);

function humanizeKey(key) {
    return String(key)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatFieldValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'number' && Number.isFinite(value)) {
        return formatAmount(value);
    }
    if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value)) && /^-?\d+(\.\d+)?$/.test(value.trim())) {
        return formatAmount(value);
    }
    return String(value);
}

function agentLabel(profile, user = null, emptyLabel = 'System') {
    if (profile?.display_name) return profile.display_name;
    if (profile) {
        const name = [profile.first_name, profile.last_name].filter(Boolean).join(' ');
        if (name) return name;
    }
    if (user) {
        if (user.first_name || user.last_name) {
            return [user.first_name, user.last_name].filter(Boolean).join(' ');
        }
        return user.name || user.username || user.email || emptyLabel;
    }
    return emptyLabel;
}

function avatarUrl(profile = null, user = null) {
    return user?.avatar_url ?? profile?.user?.avatar_url ?? null;
}

function activityAvatarUrl(activity) {
    return avatarUrl(activity?.agent_profile, activity?.actor_user);
}

function initials(name) {
    if (!name || name === 'System' || name === '—') return 'SY';
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return 'SY';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function daysElapsedLabel(days) {
    const n = Number(days);
    if (!Number.isFinite(n) || n < 0) return '0 days';
    if (n === 1) return '1 day';
    return `${n} days`;
}

function statusChipStyle(statusOrColor, textColor) {
    const bg =
        statusOrColor && typeof statusOrColor === 'object'
            ? statusOrColor.color
            : statusOrColor;
    const fg =
        statusOrColor && typeof statusOrColor === 'object'
            ? statusOrColor.text_color || '#ffffff'
            : textColor || '#ffffff';

    if (!bg) {
        return {
            background: 'transparent',
            color: 'inherit',
            borderColor: 'var(--color-border)',
        };
    }

    return {
        background: bg,
        color: fg || '#ffffff',
        borderColor: bg,
    };
}

const statusTimelineScrollEl = ref(null);

function scrollStatusTimelineToEnd() {
    nextTick(() => {
        const el = statusTimelineScrollEl.value;
        if (!el) return;
        el.scrollLeft = el.scrollWidth;
    });
}

function timelineAgents(seg) {
    return Array.isArray(seg?.agents) ? seg.agents : [];
}

/** Oldest → latest for status visualization. */
const statusTimeline = computed(() => {
    const rows = Array.isArray(props.statusExtras?.status_timeline)
        ? [...props.statusExtras.status_timeline]
        : [];
    return rows.sort((a, b) => {
        const aTime = a?.from ? new Date(a.from).getTime() : 0;
        const bTime = b?.from ? new Date(b.from).getTime() : 0;
        if (aTime !== bTime) return aTime - bTime;
        return (a?.status_id ?? 0) - (b?.status_id ?? 0);
    });
});

watch(statusTimeline, () => scrollStatusTimelineToEnd(), { immediate: true });

function typeLabel(type) {
    const match = (props.contactTypes ?? []).find((t) => t.value === type || t === type);
    return match?.label ?? type;
}

function addressTypeLabel(code) {
    const match = (props.addressTypes ?? []).find((t) => t.code === code);
    return match?.name ?? code ?? '—';
}

function contactRefLabel(contact) {
    if (!contact) return '—';
    return [typeLabel(contact.type), contact.name, contact.value].filter(Boolean).join(' · ');
}

function addressRefLabel(address) {
    if (!address) return '—';
    return [addressTypeLabel(address.type), address.line1, address.city].filter(Boolean).join(' · ');
}

function fileDownloadUrl(activity, file) {
    const activityId = activity?.id ?? file?.activity_id;
    return route('accounts.activities.files.download', [props.account.id, activityId, file.id]);
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

function daysAgoLabel(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    const startToday = new Date();
    startToday.setHours(0, 0, 0, 0);
    const startThen = new Date(date);
    startThen.setHours(0, 0, 0, 0);
    const days = Math.round((startToday - startThen) / 86400000);
    if (days <= 0) return 'today';
    if (days === 1) return '1 day ago';
    return `${days} days ago`;
}

const incomingActivityCodes = ['sms_receive', 'email_receive', 'chat_receive', 'system'];

function isIncomingActivity(activity) {
    return incomingActivityCodes.includes(activity?.activity_type?.code);
}

function activityCardStyle(activity) {
    if (isIncomingActivity(activity)) {
        return {
            background: 'linear-gradient(145deg, #f5f6f7 0%, #e8eaed 55%, #f0f1f3 100%)',
            border: '1px solid #cfd3d8',
            boxShadow: '2px 3px 8px rgba(60, 70, 80, 0.12)',
        };
    }

    return {
        background: 'linear-gradient(145deg, #fff9c4 0%, #ffe082 55%, #ffecb3 100%)',
        border: '1px solid #e6c86a',
        boxShadow: '2px 3px 8px rgba(120, 90, 20, 0.18)',
    };
}

function activityCardAccentBorder(activity) {
    return isIncomingActivity(activity)
        ? 'rgba(120, 130, 140, 0.35)'
        : 'rgba(180, 140, 40, 0.35)';
}

function activityCardAttachmentBorder(activity) {
    return isIncomingActivity(activity)
        ? 'rgba(120, 130, 140, 0.45)'
        : 'rgba(180, 140, 40, 0.45)';
}

function formatAmount(value) {
    if (value === null || value === undefined || value === '') return '—';
    const n = Number(value);
    return Number.isFinite(n)
        ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : String(value);
}

function formatDate(value) {
    if (!value) return '—';
    return String(value).slice(0, 10);
}

function formatTime(value) {
    if (!value) return '—';
    return String(value).slice(0, 5);
}

function validateFlatJson(text) {
    let parsed;
    try {
        parsed = JSON.parse(text);
    } catch (e) {
        return e.message || 'Invalid JSON';
    }
    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
        return 'JSON root must be an object, not an array or value.';
    }
    const keys = Object.keys(parsed);
    if (keys.length > 100) return 'JSON may contain at most 100 keys.';
    for (const key of keys) {
        if (!key || key.length > 100) return 'Each key must be a non-empty string up to 100 characters.';
        const value = parsed[key];
        if (value !== null && typeof value === 'object') {
            return `Nested objects or arrays are not allowed (key: ${key}).`;
        }
        if (!['string', 'number', 'boolean'].includes(typeof value) && value !== null) {
            return `Value for ${key} must be a string, number, boolean, or null.`;
        }
    }
    return '';
}

function startEditFields() {
    jsonText.value = jsonFromAccount();
    jsonClientError.value = '';
    fieldsEditing.value = true;
}

function startUploadFields() {
    startEditFields();
    fileInputRef.value?.click();
}

function cancelEditFields() {
    jsonText.value = jsonFromAccount();
    jsonClientError.value = '';
    fieldsForm.clearErrors();
    fieldsEditing.value = false;
}

function onJsonFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        jsonText.value = String(reader.result ?? '');
        jsonClientError.value = validateFlatJson(jsonText.value);
        fieldsEditing.value = true;
    };
    reader.readAsText(file);
    event.target.value = '';
}

function saveCustomFields() {
    jsonClientError.value = validateFlatJson(jsonText.value);
    if (jsonClientError.value) return;
    fieldsForm.custom_fields = jsonText.value;
    fieldsForm.put(route('accounts.custom-fields.update', props.account.id), {
        preserveScroll: true,
        onSuccess: () => {
            fieldsEditing.value = false;
        },
    });
}

function startEditStatus() {
    statusForm.entity_status_id = props.account?.entity_status_id ?? null;
    statusForm.entity_action_code_id = props.account?.entity_action_code_id ?? null;
    statusForm.last_reference_amount = props.account?.last_reference_amount ?? '';
    statusForm.last_reference_date = props.account?.last_reference_date
        ? String(props.account.last_reference_date).slice(0, 10)
        : '';
    statusForm.last_reference_time = timeFromAccount();
    statusForm.last_reference_text = props.account?.last_reference_text ?? '';
    statusForm.remarks = '';
    statusForm.clearErrors();
    statusEditing.value = true;
}

function cancelEditStatus() {
    statusEditing.value = false;
    statusForm.clearErrors();
}

function saveStatus() {
    statusForm.put(route('accounts.status.update', props.account.id), {
        preserveScroll: true,
        onSuccess: () => {
            statusEditing.value = false;
        },
    });
}

function resetContactForm() {
    contactForm.reset();
    contactForm.clearErrors();
    contactForm.type = 'email';
    contactForm.is_primary = true;
}

function openContactModal() {
    resetContactForm();
    showContactModal.value = true;
}

function closeContactModal() {
    showContactModal.value = false;
    resetContactForm();
}

function submitContact() {
    if (isNonPrimaryContact.value) {
        if (!contactForm.name?.trim()) {
            contactForm.setError('name', 'Name is required when Primary is unchecked.');
            return;
        }
        if (!contactForm.relationship?.trim()) {
            contactForm.setError('relationship', 'Relationship is required when Primary is unchecked.');
            return;
        }
    } else {
        contactForm.name = '';
        contactForm.relationship = '';
    }
    contactForm.post(route('accounts.contact-infos.store', props.account.id), {
        preserveScroll: true,
        onSuccess: () => closeContactModal(),
    });
}

function resetAddressForm() {
    addressForm.reset();
    addressForm.clearErrors();
    addressForm.type = defaultAddressTypeCode();
    addressForm.is_primary = true;
}

function openAddressModal() {
    resetAddressForm();
    showAddressModal.value = true;
}

function closeAddressModal() {
    showAddressModal.value = false;
    resetAddressForm();
}

function submitAddress() {
    if (isNonPrimaryAddress.value) {
        if (!addressForm.name?.trim()) {
            addressForm.setError('name', 'Name is required when Primary is unchecked.');
            return;
        }
        if (!addressForm.relationship?.trim()) {
            addressForm.setError('relationship', 'Relationship is required when Primary is unchecked.');
            return;
        }
    } else {
        addressForm.name = '';
        addressForm.relationship = '';
    }
    addressForm.post(route('accounts.addresses.store', props.account.id), {
        preserveScroll: true,
        onSuccess: () => closeAddressModal(),
    });
}

function openActivityModal() {
    showActivityModal.value = true;
}

function closeActivityModal() {
    showActivityModal.value = false;
}

function removeContact(id) {
    router.delete(route('accounts.contact-infos.destroy', [props.account.id, id]), { preserveScroll: true });
}

function removeAddress(id) {
    router.delete(route('accounts.addresses.destroy', [props.account.id, id]), { preserveScroll: true });
}

function openDeleteActivityModal(id) {
    pendingDeleteActivityId.value = id;
    deleteActivityConfirmText.value = '';
    deleteActivityProcessing.value = false;
    showDeleteActivityModal.value = true;
}

function closeDeleteActivityModal() {
    showDeleteActivityModal.value = false;
    pendingDeleteActivityId.value = null;
    deleteActivityConfirmText.value = '';
    deleteActivityProcessing.value = false;
}

function confirmDeleteActivity() {
    if (!canConfirmDeleteActivity.value || !pendingDeleteActivityId.value) return;
    deleteActivityProcessing.value = true;
    router.delete(route('accounts.activities.destroy', [props.account.id, pendingDeleteActivityId.value]), {
        preserveScroll: true,
        onFinish: () => {
            deleteActivityProcessing.value = false;
            closeDeleteActivityModal();
        },
    });
}

function goActivitiesPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="account.account_name || account.account_number" />
    <AppLayout>
        <template #header>{{ account.account_name || account.account_number }}</template>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_28rem] gap-4 items-start">
            <div class="space-y-4 min-w-0">
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-stretch">
                    <div class="space-y-4 min-w-0 flex flex-col">
                        <!-- Profile -->
                        <section
                            class="p-4 border rounded min-w-0 flex flex-col"
                            style="background: linear-gradient(145deg, #fafbfc 0%, #f3f4f6 55%, #f6f7f8 100%); border-color: var(--color-border)"
                        >
                            <div class="flex items-center gap-2 mb-3 text-lg font-semibold">
                                <UserRound class="h-5 w-5 shrink-0" style="color: var(--color-text-muted)" />
                                <span>Account profile</span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 flex-1">
                                <div>
                                    <div class="form-label">Entity</div>
                                    <div>{{ account.campaign?.entity?.name ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="form-label">Campaign</div>
                                    <div>{{ account.campaign?.name }}</div>
                                </div>
                                <div>
                                    <div class="form-label">Account number</div>
                                    <div>{{ account.account_number }}</div>
                                </div>
                                <div>
                                    <div class="form-label">Account name</div>
                                    <div>{{ account.account_name || '—' }}</div>
                                </div>
                                <div>
                                    <div class="form-label">Date acquired</div>
                                    <div>{{ formatDate(account.date_acquired) }}</div>
                                </div>
                                <div>
                                    <div class="form-label">Assigned agent</div>
                                    <div class="inline-flex items-center gap-2 min-w-0">
                                        <img
                                            v-if="avatarUrl(account.assigned_agent_profile)"
                                            :src="avatarUrl(account.assigned_agent_profile)"
                                            alt=""
                                            class="h-7 w-7 rounded-full object-cover shrink-0"
                                        />
                                        <span
                                            v-else-if="account.assigned_agent_profile"
                                            class="h-7 w-7 rounded-full shrink-0 flex items-center justify-center text-[10px] font-medium"
                                            style="background: var(--color-primary); color: #fff"
                                        >
                                            {{ initials(agentLabel(account.assigned_agent_profile, null, '—')) }}
                                        </span>
                                        <span class="truncate">{{ agentLabel(account.assigned_agent_profile, null, '—') }}</span>
                                    </div>
                                </div>
                                <div class="md:col-span-3">
                                    <div class="form-label">Notes</div>
                                    <div>{{ account.notes || '—' }}</div>
                                </div>
                            </div>
                            <Link v-if="can.update" :href="route('accounts.edit', account.id)" class="inline-block mt-auto pt-4">
                                <Button size="sm">Edit</Button>
                            </Link>
                        </section>

                        <!-- Account status -->
                        <section
                            class="p-4 border rounded min-w-0"
                            style="background: linear-gradient(145deg, #fafbfc 0%, #f3f4f6 55%, #f6f7f8 100%); border-color: var(--color-border)"
                        >
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2 text-lg font-semibold">
                                    <Activity class="h-5 w-5 shrink-0" style="color: var(--color-text-muted)" />
                                    <span>Account status</span>
                                </div>
                                <Button
                                    v-if="can.update && !statusEditing"
                                    size="sm"
                                    variant="secondary"
                                    @click="startEditStatus"
                                >
                                    Update
                                </Button>
                            </div>

                            <template v-if="!statusEditing">
                                <div
                                    v-if="statusTimeline.length"
                                    ref="statusTimelineScrollEl"
                                    class="min-w-0 overflow-x-auto overflow-y-hidden text-sm"
                                >
                                    <div class="inline-flex flex-row flex-nowrap items-stretch gap-1.5 pb-1">
                                        <template
                                            v-for="(seg, idx) in statusTimeline"
                                            :key="`${seg.status_id}-${idx}`"
                                        >
                                            <div
                                                class="shrink-0 flex flex-col items-center text-center p-3 min-w-[7.5rem] h-full rounded-md border box-border"
                                                style="
                                                    background: linear-gradient(180deg, #f8f9fb 0%, #eef1f4 100%);
                                                    border-color: var(--color-border);
                                                    box-shadow: 0 1px 3px rgba(44, 51, 58, 0.08);
                                                "
                                            >
                                                <div class="flex justify-center mb-1.5 min-h-[1.5rem]">
                                                    <div class="flex items-center -space-x-1.5">
                                                        <template v-if="timelineAgents(seg).length">
                                                            <template
                                                                v-for="(agent, aIdx) in timelineAgents(seg).slice(0, 3)"
                                                                :key="`${seg.status_id}-a-${aIdx}`"
                                                            >
                                                                <img
                                                                    v-if="avatarUrl(agent.agent_profile, agent.actor_user)"
                                                                    :src="avatarUrl(agent.agent_profile, agent.actor_user)"
                                                                    :title="agentLabel(agent.agent_profile, agent.actor_user)"
                                                                    alt=""
                                                                    class="h-6 w-6 rounded-full object-cover ring-1 ring-white"
                                                                />
                                                                <span
                                                                    v-else
                                                                    :title="agentLabel(agent.agent_profile, agent.actor_user)"
                                                                    class="h-6 w-6 rounded-full ring-1 ring-white flex items-center justify-center text-[9px] font-medium"
                                                                    style="background: var(--color-primary); color: #fff"
                                                                >
                                                                    {{ initials(agentLabel(agent.agent_profile, agent.actor_user)) }}
                                                                </span>
                                                            </template>
                                                            <span
                                                                v-if="timelineAgents(seg).length > 3"
                                                                class="h-6 w-6 rounded-full ring-1 ring-white flex items-center justify-center text-[9px] font-medium"
                                                                style="background: var(--color-bg-surface); color: var(--color-text-muted); border: 1px solid var(--color-border)"
                                                            >
                                                                +{{ timelineAgents(seg).length - 3 }}
                                                            </span>
                                                        </template>
                                                        <span
                                                            v-else
                                                            class="h-6 w-6 rounded-full ring-1 ring-white"
                                                            style="background: #e5e7eb; border: 1px solid var(--color-border)"
                                                            title="No assigned agent"
                                                        />
                                                    </div>
                                                </div>
                                                <span
                                                    class="inline-flex max-w-full truncate px-2 py-0.5 rounded text-xs font-medium border"
                                                    :style="statusChipStyle(seg)"
                                                >
                                                    {{ seg.name }}
                                                </span>
                                                <div class="text-xs mt-1.5 whitespace-nowrap" style="color: var(--color-text-muted)">
                                                    {{ formatDate(seg.from) }}
                                                </div>
                                                <div class="text-xs whitespace-nowrap mt-auto pt-0.5" style="color: var(--color-text-muted)">
                                                    {{ daysElapsedLabel(seg.days) }}
                                                </div>
                                            </div>
                                            <ArrowRight
                                                v-if="idx < statusTimeline.length - 1"
                                                class="h-3.5 w-3.5 shrink-0 self-center"
                                                stroke-width="1.5"
                                                style="color: var(--color-text-muted)"
                                                aria-hidden="true"
                                            />
                                        </template>
                                    </div>
                                </div>

                                <div
                                    class="mt-3 pt-3 border-t grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm"
                                    style="border-color: var(--color-border)"
                                >
                                    <div v-for="group in activityCountGroups" :key="group.label">
                                        <div class="form-label mb-1">{{ group.label }}</div>
                                        <div class="w-max max-w-full space-y-0.5">
                                            <div
                                                v-for="row in group.rows"
                                                :key="row.label"
                                                class="flex justify-between gap-3"
                                                :class="row.emphasize ? 'font-medium' : ''"
                                            >
                                                <span>{{ row.label }}</span>
                                                <span class="tabular-nums text-right">{{ row.count }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <form v-else class="space-y-3" @submit.prevent="saveStatus">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <div>
                                        <label class="form-label block mb-1">Status</label>
                                        <Select
                                            v-model="statusForm.entity_status_id"
                                            :options="entityStatuses"
                                            option-label="name"
                                            option-value="id"
                                            class="w-full"
                                            show-clear
                                        />
                                    </div>
                                    <div>
                                        <label class="form-label block mb-1">Action</label>
                                        <Select
                                            v-model="statusForm.entity_action_code_id"
                                            :options="entityActionCodes"
                                            option-label="name"
                                            option-value="id"
                                            class="w-full"
                                            show-clear
                                        />
                                    </div>
                                    <div>
                                        <label class="form-label block mb-1">Reference amount</label>
                                        <Input v-model="statusForm.last_reference_amount" type="number" step="0.01" class="w-full" />
                                    </div>
                                    <div>
                                        <label class="form-label block mb-1">Reference date</label>
                                        <Input v-model="statusForm.last_reference_date" type="date" class="w-full" />
                                    </div>
                                    <div>
                                        <label class="form-label block mb-1">Reference time</label>
                                        <Input v-model="statusForm.last_reference_time" type="time" class="w-full" />
                                    </div>
                                    <div>
                                        <label class="form-label block mb-1">Reference text</label>
                                        <Input v-model="statusForm.last_reference_text" class="w-full" />
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label block mb-1">Agent</label>
                                    <div class="text-sm py-1">{{ actorLabel || 'System' }}</div>
                                </div>
                                <div>
                                    <label class="form-label block mb-1">Remarks</label>
                                    <Textarea v-model="statusForm.remarks" rows="2" class="w-full" />
                                </div>
                                <div class="flex gap-2">
                                    <Button type="submit" size="sm" :disabled="statusForm.processing">Save</Button>
                                    <Button type="button" size="sm" variant="secondary" @click="cancelEditStatus">Cancel</Button>
                                </div>
                            </form>
                        </section>
                    </div>

                    <!-- Account fields -->
                    <section
                        class="p-4 border rounded min-w-0 h-full flex flex-col"
                        style="background: linear-gradient(145deg, #fafbfc 0%, #f3f4f6 55%, #f6f7f8 100%); border-color: var(--color-border)"
                    >
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2 text-lg font-semibold">
                                <Braces class="h-5 w-5 shrink-0" style="color: var(--color-text-muted)" />
                                <span>Account fields</span>
                            </div>
                            <div v-if="can.update && !fieldsEditing" class="flex gap-2">
                                <Button size="sm" variant="secondary" @click="startEditFields">Edit</Button>
                                <Button size="sm" variant="secondary" @click="startUploadFields">Upload</Button>
                            </div>
                        </div>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".json,application/json"
                            class="hidden"
                            @change="onJsonFile"
                        />

                        <div class="flex-1 min-h-0">
                            <template v-if="fieldsEditing">
                                <div class="space-y-3 mb-4">
                                    <div>
                                        <label class="form-label block mb-1">JSON</label>
                                        <Textarea v-model="jsonText" rows="8" class="w-full font-mono text-sm" />
                                        <InputError :message="jsonClientError || fieldsForm.errors.custom_fields" />
                                    </div>
                                    <div class="flex gap-2">
                                        <Button size="sm" :disabled="fieldsForm.processing" @click="saveCustomFields">
                                            Save
                                        </Button>
                                        <Button size="sm" variant="secondary" @click="cancelEditFields">Cancel</Button>
                                        <Button size="sm" variant="secondary" @click="fileInputRef?.click()">
                                            Choose file
                                        </Button>
                                    </div>
                                </div>
                            </template>

                            <div v-if="customFieldEntries.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="entry in customFieldEntries" :key="entry.key">
                                    <div class="form-label">{{ entry.label }}</div>
                                    <div>{{ entry.value }}</div>
                                </div>
                            </div>
                            <p v-else style="color: var(--color-text-muted)">No custom fields yet.</p>
                        </div>
                    </section>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-stretch">
                    <!-- Contacts -->
                    <section
                        class="p-4 border rounded min-w-0 h-full flex flex-col overflow-x-auto"
                        style="background: linear-gradient(145deg, #fafbfc 0%, #f3f4f6 55%, #f6f7f8 100%); border-color: var(--color-border)"
                    >
                        <div class="flex items-center justify-between gap-2 mb-3 shrink-0">
                            <div class="flex items-center gap-2 text-lg font-semibold mb-0">
                                <Contact class="h-5 w-5 shrink-0" style="color: var(--color-text-muted)" />
                                <span>Contact info</span>
                            </div>
                            <Button v-if="can.update" size="sm" @click="openContactModal">Add</Button>
                        </div>
                        <div class="flex-1 min-h-0 min-w-0">
                            <CollectimateDataTable
                                :value="contactRows"
                                :columns="contactColumns"
                                :rows="Math.max(contactRows.length, 1)"
                                :total-records="contactRows.length"
                                :first="0"
                                :paginator="false"
                            >
                                <template #cell.type="{ row }">{{ typeLabel(row.type) }}</template>
                                <template #cell.name="{ row }">{{ row.name || '—' }}</template>
                                <template #cell.relationship="{ row }">{{ row.relationship || '—' }}</template>
                                <template #cell.value="{ row }">{{ row.value || '—' }}</template>
                                <template #cell.success="{ row }">{{ row.success_count ?? 0 }}</template>
                                <template #cell.failed="{ row }">{{ row.failed_count ?? 0 }}</template>
                                <template #cell.total="{ row }">{{ row.total_count ?? 0 }}</template>
                                <template #cell.primary="{ row }">{{ row.is_primary ? 'Yes' : 'No' }}</template>
                                <template #cell.actions="{ row }">
                                    <ListingRowActions v-if="can.update" :on-delete="() => removeContact(row.id)" />
                                </template>
                            </CollectimateDataTable>
                        </div>
                    </section>

                    <!-- Addresses -->
                    <section
                        class="p-4 border rounded min-w-0 h-full flex flex-col overflow-x-auto"
                        style="background: linear-gradient(145deg, #fafbfc 0%, #f3f4f6 55%, #f6f7f8 100%); border-color: var(--color-border)"
                    >
                        <div class="flex items-center justify-between gap-2 mb-3 shrink-0">
                            <div class="flex items-center gap-2 text-lg font-semibold mb-0">
                                <MapPin class="h-5 w-5 shrink-0" style="color: var(--color-text-muted)" />
                                <span>Addresses</span>
                            </div>
                            <Button v-if="can.update" size="sm" @click="openAddressModal">Add</Button>
                        </div>
                        <div class="flex-1 min-h-0 min-w-0">
                            <CollectimateDataTable
                                :value="addressRows"
                                :columns="addressColumns"
                                :rows="Math.max(addressRows.length, 1)"
                                :total-records="addressRows.length"
                                :first="0"
                                :paginator="false"
                            >
                                <template #cell.type="{ row }">{{ addressTypeLabel(row.type) }}</template>
                                <template #cell.name="{ row }">{{ row.name || '—' }}</template>
                                <template #cell.relationship="{ row }">{{ row.relationship || '—' }}</template>
                                <template #cell.line1="{ row }">{{ row.line1 }}</template>
                                <template #cell.line2="{ row }">{{ row.line2 || '—' }}</template>
                                <template #cell.city="{ row }">{{ row.city || '—' }}</template>
                                <template #cell.state="{ row }">{{ row.state || '—' }}</template>
                                <template #cell.postal="{ row }">{{ row.postal_code || '—' }}</template>
                                <template #cell.country="{ row }">{{ row.country || '—' }}</template>
                                <template #cell.remarks="{ row }">{{ row.remarks || '—' }}</template>
                                <template #cell.primary="{ row }">{{ row.is_primary ? 'Yes' : 'No' }}</template>
                                <template #cell.actions="{ row }">
                                    <ListingRowActions v-if="can.update" :on-delete="() => removeAddress(row.id)" />
                                </template>
                            </CollectimateDataTable>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Activities -->
            <aside
                class="border rounded flex flex-col lg:sticky lg:top-4 lg:h-[calc(100vh-5.5rem)] min-h-[28rem]"
                style="background: linear-gradient(145deg, #fafbfc 0%, #f3f4f6 55%, #f6f7f8 100%); border-color: var(--color-border)"
            >
                <div class="flex items-center justify-between gap-2 px-4 pt-4 pb-2 shrink-0">
                    <div class="flex items-center gap-2 text-lg font-semibold mb-0">
                        <StickyNote class="h-5 w-5 shrink-0" style="color: var(--color-text-muted)" />
                        <span>Activities</span>
                    </div>
                    <Button v-if="can.update" size="sm" @click="openActivityModal">Add</Button>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto px-3 py-3 space-y-2.5">
                    <div
                        v-for="activity in activityRows"
                        :key="activity.id"
                        class="rounded-sm p-2.5 text-xs"
                        :style="activityCardStyle(activity)"
                    >
                        <div class="flex gap-2">
                            <img
                                v-if="activityAvatarUrl(activity)"
                                :src="activityAvatarUrl(activity)"
                                alt=""
                                class="h-9 w-9 rounded-full object-cover shrink-0"
                            />
                            <div
                                v-else
                                class="h-9 w-9 rounded-full shrink-0 flex items-center justify-center text-[11px] font-medium"
                                style="background: var(--color-primary); color: #fff"
                            >
                                {{
                                    initials(
                                        agentLabel(activity.agent_profile, activity.actor_user),
                                    )
                                }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex justify-between gap-2 items-start">
                                    <div class="min-w-0">
                                        <div class="font-medium truncate">
                                            {{ agentLabel(activity.agent_profile, activity.actor_user) }}
                                        </div>
                                        <div class="mt-0.5" style="color: var(--color-text-muted)">
                                            {{ formatDateTime(activity.occurred_at) }}
                                        </div>
                                        <div class="mt-0.5" style="color: var(--color-text-muted)">
                                            {{ daysAgoLabel(activity.occurred_at) }}
                                        </div>
                                        <div
                                            v-if="activity.files?.length"
                                            class="flex flex-wrap items-center gap-1.5 mt-1.5"
                                        >
                                            <a
                                                v-for="file in activity.files"
                                                :key="file.id"
                                                :href="fileDownloadUrl(activity, file)"
                                                :title="file.original_name"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded border hover:opacity-80"
                                                :style="{
                                                    borderColor: activityCardAttachmentBorder(activity),
                                                    color: 'var(--color-primary)',
                                                }"
                                            >
                                                <FileImage v-if="isImageAttachment(file)" class="h-3.5 w-3.5" />
                                                <FileText v-else class="h-3.5 w-3.5" />
                                            </a>
                                        </div>
                                    </div>
                                    <div class="shrink-0 flex flex-col items-end gap-1 text-right max-w-[45%]">
                                        <span
                                            class="inline-flex max-w-full truncate px-1.5 py-0.5 rounded text-[11px] font-medium border"
                                            :style="statusChipStyle(activity.entity_status)"
                                        >
                                            {{ activity.entity_status?.name || '—' }}
                                        </span>
                                        <div class="truncate max-w-full" style="color: var(--color-text-muted)">
                                            {{ activity.entity_action_code?.name || '—' }}
                                        </div>
                                        <div class="truncate max-w-full" style="color: var(--color-text-muted)">
                                            {{ activity.activity_type?.name ?? '—' }}
                                        </div>
                                        <div
                                            v-if="activity.entity_template?.slug"
                                            class="truncate max-w-full"
                                            style="color: var(--color-text-muted)"
                                            :title="`Template: ${activity.entity_template.slug}`"
                                        >
                                            Template: {{ activity.entity_template.slug }}
                                        </div>
                                        <ListingRowActions
                                            v-if="can.update"
                                            :on-delete="() => openDeleteActivityModal(activity.id)"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="mt-2 pt-2 border-t space-y-1.5"
                                    :style="{ borderColor: activityCardAccentBorder(activity) }"
                                >
                                    <div
                                        v-if="
                                            activity.reference_text ||
                                            activity.reference_amount != null ||
                                            activity.reference_date
                                        "
                                        class="flex flex-wrap gap-x-3 gap-y-1"
                                    >
                                        <div
                                            v-if="activity.reference_text"
                                            class="inline-flex items-center gap-1 min-w-0"
                                            title="Reference"
                                        >
                                            <TextQuote class="h-3.5 w-3.5 shrink-0" style="color: var(--color-text-muted)" />
                                            <span class="truncate">{{ activity.reference_text }}</span>
                                        </div>
                                        <div
                                            v-if="activity.reference_amount != null"
                                            class="inline-flex items-center gap-1"
                                            title="Amount"
                                        >
                                            <Banknote class="h-3.5 w-3.5 shrink-0" style="color: var(--color-text-muted)" />
                                            <span>{{ formatAmount(activity.reference_amount) }}</span>
                                        </div>
                                        <div
                                            v-if="activity.reference_date"
                                            class="inline-flex items-center gap-1"
                                            title="Date / time"
                                        >
                                            <CalendarClock class="h-3.5 w-3.5 shrink-0" style="color: var(--color-text-muted)" />
                                            <span>
                                                {{ formatDate(activity.reference_date) }}
                                                {{ formatTime(activity.reference_time) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        v-if="activity.reference_contact_info"
                                        class="flex items-start gap-1 min-w-0"
                                        title="Contact"
                                    >
                                        <Contact class="h-3.5 w-3.5 shrink-0 mt-0.5" style="color: var(--color-text-muted)" />
                                        <span class="truncate">{{ contactRefLabel(activity.reference_contact_info) }}</span>
                                    </div>
                                    <div
                                        v-if="activity.reference_address"
                                        class="flex items-start gap-1 min-w-0"
                                        title="Address"
                                    >
                                        <MapPin class="h-3.5 w-3.5 shrink-0 mt-0.5" style="color: var(--color-text-muted)" />
                                        <span class="truncate">{{ addressRefLabel(activity.reference_address) }}</span>
                                    </div>
                                    <div
                                        v-if="activity.remarks"
                                        class="flex items-start gap-1 min-w-0"
                                        title="Remarks"
                                    >
                                        <MessageSquare class="h-3.5 w-3.5 shrink-0 mt-0.5" style="color: var(--color-text-muted)" />
                                        <span>{{ activity.remarks }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="!activityRows.length" style="color: var(--color-text-muted)">No activities yet.</p>
                </div>

                <div
                    v-if="activities?.last_page > 1"
                    class="px-3 py-2 border-t flex items-center justify-between gap-2 text-xs shrink-0"
                    style="border-color: var(--color-border)"
                >
                    <Button
                        size="sm"
                        variant="secondary"
                        :disabled="!activities.prev_page_url"
                        @click="goActivitiesPage(activities.prev_page_url)"
                    >
                        Prev
                    </Button>
                    <span style="color: var(--color-text-muted)">
                        {{ activities.current_page }} / {{ activities.last_page }}
                    </span>
                    <Button
                        size="sm"
                        variant="secondary"
                        :disabled="!activities.next_page_url"
                        @click="goActivitiesPage(activities.next_page_url)"
                    >
                        Next
                    </Button>
                </div>
            </aside>
        </div>

        <Modal :show="showContactModal" max-width="lg" @close="closeContactModal">
            <form class="p-6 space-y-4" @submit.prevent="submitContact">
                <h2 class="text-lg font-semibold">Add contact</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="form-label block mb-1">Type</label>
                        <Select v-model="contactForm.type" :options="contactTypes" class="w-full" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Value</label>
                        <Input v-model="contactForm.value" class="w-full" :placeholder="valuePlaceholder" />
                        <InputError :message="contactForm.errors.value" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Name</label>
                        <Input v-model="contactForm.name" class="w-full" :disabled="!isNonPrimaryContact" />
                        <InputError :message="contactForm.errors.name" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Relationship</label>
                        <Input v-model="contactForm.relationship" class="w-full" :disabled="!isNonPrimaryContact" />
                        <InputError :message="contactForm.errors.relationship" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="contactForm.is_primary" />
                    <span class="text-sm">Primary</span>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeContactModal">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="contactForm.processing">Save</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showAddressModal" max-width="2xl" @close="closeAddressModal">
            <form class="p-6 space-y-4" @submit.prevent="submitAddress">
                <h2 class="text-lg font-semibold">Add address</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="form-label block mb-1">Type</label>
                        <Select
                            v-model="addressForm.type"
                            :options="addressTypes"
                            option-label="name"
                            option-value="code"
                            class="w-full"
                        />
                        <InputError :message="addressForm.errors.type" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Name</label>
                        <Input v-model="addressForm.name" class="w-full" :disabled="!isNonPrimaryAddress" />
                        <InputError :message="addressForm.errors.name" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Relationship</label>
                        <Input v-model="addressForm.relationship" class="w-full" :disabled="!isNonPrimaryAddress" />
                        <InputError :message="addressForm.errors.relationship" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Line 1</label>
                        <Input v-model="addressForm.line1" class="w-full" />
                        <InputError :message="addressForm.errors.line1" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Line 2</label>
                        <Input v-model="addressForm.line2" class="w-full" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">City</label>
                        <Input v-model="addressForm.city" class="w-full" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">State</label>
                        <Input v-model="addressForm.state" class="w-full" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Postal</label>
                        <Input v-model="addressForm.postal_code" class="w-full" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Country</label>
                        <Input v-model="addressForm.country" class="w-full" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Remarks</label>
                        <Input v-model="addressForm.remarks" class="w-full" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="addressForm.is_primary" />
                    <span class="text-sm">Primary</span>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeAddressModal">Cancel</Button>
                    <Button type="submit" size="sm" :disabled="addressForm.processing">Save</Button>
                </div>
            </form>
        </Modal>

        <AccountActivityFormModal
            :show="showActivityModal"
            mode="single"
            :account="account"
            :activity-types="activityTypes"
            :entity-statuses="entityStatuses"
            :entity-action-codes="entityActionCodes"
            :entity-templates="entityTemplates"
            :contact-options="contactSelectOptions"
            :address-options="addressSelectOptions"
            :actor-label="actorLabel"
            :submit-url="route('accounts.activities.store', account.id)"
            @close="closeActivityModal"
        />

        <Modal :show="showDeleteActivityModal" max-width="md" @close="closeDeleteActivityModal">
            <div class="p-6 space-y-4">
                <h2 class="text-lg font-semibold">Delete activity</h2>
                <p class="text-sm" style="color: var(--color-text-muted)">
                    This will remove the activity. Type
                    <span class="font-mono font-medium" style="color: var(--color-text)">DELETEACTIVITY</span>
                    to confirm.
                </p>
                <div>
                    <label class="form-label block mb-1" for="delete-activity-confirm">Confirmation</label>
                    <Input
                        id="delete-activity-confirm"
                        v-model="deleteActivityConfirmText"
                        class="w-full font-mono"
                        autocomplete="off"
                        placeholder="DELETEACTIVITY"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeDeleteActivityModal">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        :disabled="!canConfirmDeleteActivity || deleteActivityProcessing"
                        @click="confirmDeleteActivity"
                    >
                        Delete
                    </Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
