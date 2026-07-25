<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AccountActivityFormModal from '@/Components/AccountActivityFormModal.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import InputError from '@/Components/InputError.vue';
import ListingPage from '@/Components/ListingPage.vue';
import ListingRowActions from '@/Components/ListingRowActions.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    accounts: Object,
    filters: Object,
    filterOptions: Object,
    can: Object,
    activityTypes: {
        type: Array,
        default: () => [],
    },
    actorLabel: {
        type: String,
        default: '',
    },
});

const { navigate, onPage, onSort, onSearch, onClear } = useListingNavigation(props.filters, 'accounts.index');

const entityId = ref(props.filters?.entity_id ?? null);
const campaignId = ref(props.filters?.campaign_id ?? null);
const entityStatusId = ref(props.filters?.entity_status_id ?? null);
const entityActionCodeId = ref(props.filters?.entity_action_code_id ?? null);
const lastAgentId = ref(props.filters?.last_activity_agent_profile_id ?? null);

const selectedIds = ref([]);
const bulkScope = ref('selected');
const activeBulkOp = ref(null);
const bulkOptions = ref(null);
const bulkOptionsLoading = ref(false);
const bulkOptionsError = ref(null);

const bulkForm = useForm({
    scope: 'selected',
    account_ids: [],
    remarks: '',
    campaign_id: null,
    assigned_agent_profile_id: null,
    entity_status_id: null,
    entity_action_code_id: null,
    activity_type_id: null,
    filters: {},
});

watch(
    () => props.filters,
    (filters) => {
        entityId.value = filters?.entity_id ?? null;
        campaignId.value = filters?.campaign_id ?? null;
        entityStatusId.value = filters?.entity_status_id ?? null;
        entityActionCodeId.value = filters?.entity_action_code_id ?? null;
        lastAgentId.value = filters?.last_activity_agent_profile_id ?? null;
        selectedIds.value = [];
    },
    { deep: true },
);

const entityOptions = computed(() => props.filterOptions?.entities ?? []);
const campaignOptions = computed(() => props.filterOptions?.campaigns ?? []);
const statusOptions = computed(() => props.filterOptions?.statuses ?? []);
const actionOptions = computed(() => props.filterOptions?.actions ?? []);
const lastAgentOptions = computed(() => props.filterOptions?.lastAgents ?? []);

const filteredTotal = computed(() => props.accounts?.total ?? 0);
const selectedCount = computed(() => selectedIds.value.length);
const targetCount = computed(() =>
    bulkScope.value === 'all' ? filteredTotal.value : selectedCount.value,
);
const canBulk = computed(() => !!props.can?.update && targetCount.value > 0);

const bulkOpTitle = computed(() => {
    switch (activeBulkOp.value) {
        case 'campaign':
            return 'Assign campaign';
        case 'agent':
            return 'Assign agent';
        case 'status':
            return 'Assign status';
        default:
            return 'Bulk operation';
    }
});

const showBulkActivityModal = computed(() => activeBulkOp.value === 'activity');
const showSimpleBulkModal = computed(
    () => !!activeBulkOp.value && activeBulkOp.value !== 'activity',
);

const modalCampaignOptions = computed(() => bulkOptions.value?.campaigns ?? []);
const modalAgentOptions = computed(() => bulkOptions.value?.agents ?? []);
const modalStatusOptions = computed(() => bulkOptions.value?.statuses ?? []);
const modalActionOptions = computed(() => bulkOptions.value?.actions ?? []);
const modalEntityName = computed(() => bulkOptions.value?.entity?.name ?? '—');
const modalTemplates = computed(() => bulkOptions.value?.templates ?? []);
const modalActorLabel = computed(() => bulkOptions.value?.actor_label || props.actorLabel || '');

const bulkActivitySubtitle = computed(() => {
    const entity = modalEntityName.value !== '—' ? modalEntityName.value : 'one Entity';
    return `Applying to ${targetCount.value} account(s) (${bulkScope.value === 'all' ? 'all filtered' : 'selected'}) under ${entity}. Remarks required.`;
});

const bulkActivityExtraPayload = computed(() => ({
    scope: bulkScope.value,
    account_ids: bulkScope.value === 'selected' ? [...selectedIds.value] : [],
    filters: filterQueryParams(),
}));

const bulkActivitySubmitUrl = computed(() => route('accounts.bulk.activity', {}, false));

const columns = [
    { id: 'account_number', accessorKey: 'account_number', header: 'Account #', sortable: true },
    { id: 'account_name', accessorKey: 'account_name', header: 'Account name', sortable: true },
    { id: 'entity', header: 'Entity' },
    { id: 'campaign', header: 'Campaign' },
    { id: 'assigned_agent', header: 'Assigned agent' },
    { id: 'product', accessorKey: 'product', header: 'Product', sortable: true },
    { id: 'activities', header: 'Activities' },
    { id: 'positive_activity_count', accessorKey: 'positive_activity_count', header: '+Pos', sortable: true },
    { id: 'negative_activity_count', accessorKey: 'negative_activity_count', header: '-Neg', sortable: true },
    { id: 'neutral_activity_count', accessorKey: 'neutral_activity_count', header: '~Neutral', sortable: true },
    { id: 'sms_out_count', accessorKey: 'sms_out_count', header: 'SMS out', sortable: true },
    { id: 'sms_in_count', accessorKey: 'sms_in_count', header: 'SMS in', sortable: true },
    { id: 'call_success_count', accessorKey: 'call_success_count', header: 'Call success', sortable: true },
    { id: 'call_failed_count', accessorKey: 'call_failed_count', header: 'Call failed', sortable: true },
    { id: 'call_total_count', accessorKey: 'call_total_count', header: 'Call total', sortable: true },
    { id: 'last_activity', header: 'Last activity' },
    { id: 'days_ago', header: 'Days ago' },
    { id: 'status', header: 'Status' },
    { id: 'action', header: 'Action' },
    { id: 'actions', header: 'Actions' },
];

function applyFilters(overrides = {}) {
    selectedIds.value = [];
    navigate({
        entity_id: entityId.value || undefined,
        campaign_id: campaignId.value || undefined,
        entity_status_id: entityStatusId.value || undefined,
        entity_action_code_id: entityActionCodeId.value || undefined,
        last_activity_agent_profile_id: lastAgentId.value || undefined,
        page: 1,
        ...overrides,
    });
}

function onEntityChange(value) {
    entityId.value = value;
    campaignId.value = null;
    entityStatusId.value = null;
    entityActionCodeId.value = null;
    lastAgentId.value = null;
    applyFilters({
        entity_id: value || undefined,
        campaign_id: undefined,
        entity_status_id: undefined,
        entity_action_code_id: undefined,
        last_activity_agent_profile_id: undefined,
    });
}

function onCampaignChange(value) {
    campaignId.value = value;
    applyFilters({ campaign_id: value || undefined });
}

function onStatusChange(value) {
    entityStatusId.value = value;
    applyFilters({ entity_status_id: value || undefined });
}

function onActionChange(value) {
    entityActionCodeId.value = value;
    applyFilters({ entity_action_code_id: value || undefined });
}

function onLastAgentChange(value) {
    lastAgentId.value = value;
    applyFilters({ last_activity_agent_profile_id: value || undefined });
}

function handleSearch(value) {
    selectedIds.value = [];
    onSearch(value);
}

function handleClear() {
    selectedIds.value = [];
    onClear();
}

function setBulkScope(value) {
    bulkScope.value = value || 'selected';
}

function agentLabel(profile) {
    if (!profile) return '—';
    return profile.display_name
        || [profile.first_name, profile.last_name].filter(Boolean).join(' ')
        || '—';
}

function agentAvatarUrl(profile) {
    return profile?.user?.avatar_url ?? null;
}

function initials(name) {
    if (!name || name === '—') return '?';
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString();
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

function exportUrl() {
    const params = new URLSearchParams();
    Object.entries(props.filters ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });
    const query = params.toString();
    return route('accounts.export') + (query ? `?${query}` : '');
}

function filterQueryParams() {
    const params = {};
    Object.entries(props.filters ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params[key] = value;
        }
    });
    return params;
}

function bulkPayloadBase() {
    return {
        scope: bulkScope.value,
        account_ids: bulkScope.value === 'selected' ? [...selectedIds.value] : [],
        ...filterQueryParams(),
    };
}

async function loadBulkOptions(op) {
    bulkOptionsLoading.value = true;
    bulkOptionsError.value = null;
    bulkOptions.value = null;

    try {
        const { data } = await window.axios.get(route('accounts.bulk.options', {}, false), {
            params: bulkPayloadBase(),
        });
        bulkOptions.value = data;
        if (data.error) {
            bulkOptionsError.value = data.error;
        } else if (op === 'agent' && (data.agents?.length ?? 0) === 0) {
            bulkOptionsError.value = 'No agent is assigned to every campaign in this set.';
        } else if ((op === 'campaign' || op === 'status' || op === 'activity') && !data.entity) {
            bulkOptionsError.value = 'Selected accounts must belong to the same Entity.';
        }
    } catch (error) {
        const message = error?.response?.data?.errors?.bulk?.[0]
            || error?.response?.data?.message
            || error?.message
            || 'Unable to load bulk options.';
        bulkOptionsError.value = message;
    } finally {
        bulkOptionsLoading.value = false;
    }
}

async function openBulkModal(op) {
    if (!canBulk.value) return;

    activeBulkOp.value = op;
    bulkForm.clearErrors();
    bulkForm.reset();
    bulkForm.scope = bulkScope.value;
    bulkForm.account_ids = bulkScope.value === 'selected' ? [...selectedIds.value] : [];
    bulkForm.remarks = '';
    bulkForm.campaign_id = null;
    bulkForm.assigned_agent_profile_id = null;
    bulkForm.entity_status_id = null;
    bulkForm.entity_action_code_id = null;
    bulkForm.activity_type_id = null;

    await loadBulkOptions(op);
}

function closeBulkModal() {
    activeBulkOp.value = null;
    bulkOptions.value = null;
    bulkOptionsError.value = null;
    bulkForm.clearErrors();
}

function submitBulk() {
    if (!activeBulkOp.value || activeBulkOp.value === 'activity' || bulkOptionsError.value) return;

    bulkForm.scope = bulkScope.value;
    bulkForm.account_ids = bulkScope.value === 'selected' ? [...selectedIds.value] : [];
    bulkForm.filters = filterQueryParams();

    const routeName = {
        campaign: 'accounts.bulk.campaign',
        agent: 'accounts.bulk.agent',
        status: 'accounts.bulk.status',
    }[activeBulkOp.value];

    bulkForm
        .transform((data) => {
            const payload = {
                scope: data.scope,
                account_ids: data.account_ids,
                remarks: data.remarks,
                filters: data.filters,
            };

            if (activeBulkOp.value === 'campaign') {
                payload.campaign_id = data.campaign_id;
            }
            if (activeBulkOp.value === 'agent') {
                payload.assigned_agent_profile_id = data.assigned_agent_profile_id;
            }
            if (activeBulkOp.value === 'status') {
                payload.entity_status_id = data.entity_status_id;
                payload.entity_action_code_id = data.entity_action_code_id;
            }

            return payload;
        })
        .post(route(routeName, {}, false), {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                closeBulkModal();
            },
        });
}

function onBulkActivitySuccess() {
    selectedIds.value = [];
    closeBulkModal();
}
</script>

<template>
    <Head title="Account Master" />
    <AppLayout>
        <template #header>Account Master</template>
        <ListingPage
            title="Accounts"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :can-create="can.create"
            :create-href="route('accounts.create')"
            :export-href="exportUrl()"
            @search="handleSearch"
            @clear="handleClear"
        >
            <template #filters>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Entity</label>
                    <Select
                        :model-value="entityId"
                        :options="entityOptions"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="onEntityChange"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Campaign</label>
                    <Select
                        :model-value="campaignId"
                        :options="campaignOptions"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        :disabled="!entityId"
                        class="w-full min-w-[10rem]"
                        @update:model-value="onCampaignChange"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Status</label>
                    <Select
                        :model-value="entityStatusId"
                        :options="statusOptions"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        :disabled="!entityId"
                        class="w-full min-w-[10rem]"
                        @update:model-value="onStatusChange"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Action</label>
                    <Select
                        :model-value="entityActionCodeId"
                        :options="actionOptions"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        :disabled="!entityId"
                        class="w-full min-w-[10rem]"
                        @update:model-value="onActionChange"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Last agent</label>
                    <Select
                        :model-value="lastAgentId"
                        :options="lastAgentOptions"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        :disabled="!entityId"
                        class="w-full min-w-[10rem]"
                        @update:model-value="onLastAgentChange"
                    />
                </div>

                <div
                    v-if="can.update"
                    class="ml-auto flex flex-wrap items-end gap-2 border-l pl-3"
                    style="border-color: var(--color-border)"
                >
                    <div class="min-w-[9rem]">
                        <label class="form-label block mb-1">Bulk scope</label>
                        <Select
                            :model-value="bulkScope"
                            :options="[
                                { id: 'selected', name: `Selected (${selectedCount})` },
                                { id: 'all', name: `All filtered (${filteredTotal})` },
                            ]"
                            option-label="name"
                            option-value="id"
                            class="w-full min-w-[11rem]"
                            @update:model-value="setBulkScope"
                        />
                    </div>
                    <Button size="sm" variant="secondary" :disabled="!canBulk" @click="openBulkModal('campaign')">
                        Assign campaign
                    </Button>
                    <Button size="sm" variant="secondary" :disabled="!canBulk" @click="openBulkModal('agent')">
                        Assign agent
                    </Button>
                    <Button size="sm" variant="secondary" :disabled="!canBulk" @click="openBulkModal('status')">
                        Assign status
                    </Button>
                    <Button size="sm" variant="secondary" :disabled="!canBulk" @click="openBulkModal('activity')">
                        Add activity
                    </Button>
                </div>
            </template>

            <CollectimateDataTable
                v-model:selected-ids="selectedIds"
                :value="accounts.data"
                :columns="columns"
                :rows="accounts.per_page"
                :total-records="accounts.total"
                :first="(accounts.current_page - 1) * accounts.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                :selectable="!!can.update"
                show-row-numbers
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.entity="{ row }">{{ row.campaign?.entity?.name }}</template>
                <template #cell.campaign="{ row }">{{ row.campaign?.name }}</template>
                <template #cell.assigned_agent="{ row }">
                    <div class="inline-flex items-center gap-2 min-w-0">
                        <img
                            v-if="agentAvatarUrl(row.assigned_agent_profile)"
                            :src="agentAvatarUrl(row.assigned_agent_profile)"
                            alt=""
                            class="h-7 w-7 rounded-full object-cover shrink-0"
                        />
                        <span
                            v-else-if="row.assigned_agent_profile"
                            class="h-7 w-7 rounded-full shrink-0 flex items-center justify-center text-[10px] font-medium"
                            style="background: var(--color-primary); color: #fff"
                        >
                            {{ initials(agentLabel(row.assigned_agent_profile)) }}
                        </span>
                        <span class="truncate">{{ agentLabel(row.assigned_agent_profile) }}</span>
                    </div>
                </template>
                <template #cell.activities="{ row }">{{ row.activities_count ?? 0 }}</template>
                <template #cell.positive_activity_count="{ row }">{{ row.positive_activity_count ?? 0 }}</template>
                <template #cell.negative_activity_count="{ row }">{{ row.negative_activity_count ?? 0 }}</template>
                <template #cell.neutral_activity_count="{ row }">{{ row.neutral_activity_count ?? 0 }}</template>
                <template #cell.sms_out_count="{ row }">{{ row.sms_out_count ?? 0 }}</template>
                <template #cell.sms_in_count="{ row }">{{ row.sms_in_count ?? 0 }}</template>
                <template #cell.call_success_count="{ row }">{{ row.call_success_count ?? 0 }}</template>
                <template #cell.call_failed_count="{ row }">{{ row.call_failed_count ?? 0 }}</template>
                <template #cell.call_total_count="{ row }">{{ row.call_total_count ?? 0 }}</template>
                <template #cell.last_activity="{ row }">
                    {{ formatDate(row.last_activity_at) }}
                </template>
                <template #cell.days_ago="{ row }">
                    {{ daysAgoLabel(row.last_activity_at) }}
                </template>
                <template #cell.status="{ row }">{{ row.entity_status?.name ?? '—' }}</template>
                <template #cell.action="{ row }">{{ row.entity_action_code?.name ?? '—' }}</template>
                <template #cell.actions="{ row }">
                    <ListingRowActions :view-href="route('accounts.show', row.id)" />
                </template>
            </CollectimateDataTable>
        </ListingPage>

        <Modal
            :show="showSimpleBulkModal || (showBulkActivityModal && (bulkOptionsLoading || !!bulkOptionsError))"
            max-width="lg"
            @close="closeBulkModal"
        >
            <div class="p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold" style="color: var(--color-text)">
                        {{ activeBulkOp === 'activity' ? 'Add activity' : bulkOpTitle }}
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--color-text-muted)">
                        Applying to {{ targetCount }} account(s)
                        ({{ bulkScope === 'all' ? 'all filtered' : 'selected' }}).
                    </p>
                </div>

                <div v-if="bulkOptionsLoading" class="text-sm" style="color: var(--color-text-muted)">
                    Loading options…
                </div>

                <div
                    v-else-if="bulkOptionsError"
                    class="text-sm rounded border px-3 py-2"
                    style="border-color: var(--color-danger, #b91c1c); color: var(--color-danger, #b91c1c)"
                >
                    {{ bulkOptionsError }}
                </div>

                <template v-else>
                    <div v-if="activeBulkOp === 'campaign' || activeBulkOp === 'status'">
                        <label class="form-label block mb-1">Entity</label>
                        <div class="text-sm py-2">{{ modalEntityName }}</div>
                    </div>

                    <div v-if="activeBulkOp === 'campaign'">
                        <label class="form-label block mb-1">Campaign <span class="text-red-600">*</span></label>
                        <Select
                            v-model="bulkForm.campaign_id"
                            :options="modalCampaignOptions"
                            option-label="name"
                            option-value="id"
                            placeholder="Select campaign"
                            class="w-full"
                        />
                        <InputError :message="bulkForm.errors.campaign_id" />
                    </div>

                    <div v-if="activeBulkOp === 'agent'">
                        <label class="form-label block mb-1">Agent <span class="text-red-600">*</span></label>
                        <Select
                            v-model="bulkForm.assigned_agent_profile_id"
                            :options="modalAgentOptions"
                            option-label="name"
                            option-value="id"
                            placeholder="Select agent"
                            class="w-full"
                        />
                        <InputError :message="bulkForm.errors.assigned_agent_profile_id || bulkForm.errors.bulk" />
                        <p v-if="modalAgentOptions.length === 0" class="text-sm mt-1" style="color: var(--color-text-muted)">
                            No agent is assigned to every campaign in this set.
                        </p>
                    </div>

                    <div v-if="activeBulkOp === 'status'" class="space-y-3">
                        <div>
                            <label class="form-label block mb-1">Status <span class="text-red-600">*</span></label>
                            <Select
                                v-model="bulkForm.entity_status_id"
                                :options="modalStatusOptions"
                                option-label="name"
                                option-value="id"
                                placeholder="Select status"
                                class="w-full"
                            />
                            <InputError :message="bulkForm.errors.entity_status_id" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">Action</label>
                            <Select
                                v-model="bulkForm.entity_action_code_id"
                                :options="modalActionOptions"
                                option-label="name"
                                option-value="id"
                                placeholder="Optional"
                                show-clear
                                class="w-full"
                            />
                            <InputError :message="bulkForm.errors.entity_action_code_id" />
                        </div>
                    </div>

                    <div>
                        <label class="form-label block mb-1">Comment <span class="text-red-600">*</span></label>
                        <Textarea v-model="bulkForm.remarks" rows="3" class="w-full" />
                        <InputError :message="bulkForm.errors.remarks || bulkForm.errors.bulk" />
                    </div>
                </template>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="secondary" size="sm" @click="closeBulkModal">Cancel</Button>
                    <Button
                        v-if="activeBulkOp !== 'activity'"
                        type="button"
                        size="sm"
                        :disabled="bulkForm.processing || bulkOptionsLoading || !!bulkOptionsError"
                        @click="submitBulk"
                    >
                        Apply
                    </Button>
                </div>
            </div>
        </Modal>

        <AccountActivityFormModal
            :show="showBulkActivityModal && !bulkOptionsLoading && !bulkOptionsError"
            mode="bulk"
            :activity-types="activityTypes"
            :entity-statuses="modalStatusOptions"
            :entity-action-codes="modalActionOptions"
            :entity-templates="modalTemplates"
            :actor-label="modalActorLabel"
            :submit-url="bulkActivitySubmitUrl"
            :extra-payload="bulkActivityExtraPayload"
            :subtitle="bulkActivitySubtitle"
            remarks-required
            @close="closeBulkModal"
            @success="onBulkActivitySuccess"
        />
    </AppLayout>
</template>
