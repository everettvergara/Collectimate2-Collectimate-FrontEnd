<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CollectimateDataTable from '@/Components/CollectimateDataTable.vue';
import ListingPage from '@/Components/ListingPage.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { useListingNavigation } from '@/Composables/useListingNavigation';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    messages: Object,
    filters: Object,
    filterOptions: Object,
    replyDevices: { type: Array, default: () => [] },
    can: Object,
});

const { navigate, onPage, onSort, onSearch, onClear } = useListingNavigation(
    props.filters,
    'sms.received.index',
);

const columns = [
    { id: 'id', accessorKey: 'id', header: 'ID', sortable: true },
    { id: 'received_at', accessorKey: 'received_at', header: 'Received', sortable: true },
    { id: 'sender', accessorKey: 'sender', header: 'Sender', sortable: true },
    { id: 'message_preview', header: 'Message' },
    { id: 'device_id', accessorKey: 'device_id', header: 'Device', sortable: true },
    { id: 'association_status', accessorKey: 'association_status', header: 'Association', sortable: true },
    { id: 'account', header: 'Account' },
    { id: 'actions', header: 'Actions' },
];

const viewMessage = ref(null);
const associateMessage = ref(null);
const replyMessage = ref(null);

const associateQ = ref('');
const associateResults = ref([]);
const associateAccountId = ref(null);
const associateLabel = ref('');
const associateLoading = ref(false);
let associateTimer = null;

const replyBody = ref('');
const replyDevice = ref('');
const replyLoading = ref(false);
const ignoreLoading = ref({});
const deleteLoading = ref({});

function exportUrl() {
    const params = new URLSearchParams();
    Object.entries(props.filters ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, value);
        }
    });
    const qs = params.toString();
    return route('sms.received.export') + (qs ? `?${qs}` : '');
}

function applyFilter(key, value) {
    navigate({ [key]: value || undefined, page: 1 });
}

function formatWhen(value) {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
}

function openView(row) {
    viewMessage.value = row;
}

function closeView() {
    viewMessage.value = null;
}

function openAssociate(row) {
    associateMessage.value = row;
    associateQ.value = '';
    associateResults.value = [];
    associateAccountId.value = null;
    associateLabel.value = '';
}

function closeAssociate() {
    associateMessage.value = null;
}

function openReply(row) {
    replyMessage.value = row;
    replyBody.value = '';
    replyDevice.value = row.device_id || props.replyDevices[0]?.id || '';
}

function closeReply() {
    replyMessage.value = null;
}

async function searchAccounts(q) {
    associateQ.value = q;
    if (associateTimer) clearTimeout(associateTimer);
    if ((q || '').trim().length < 1) {
        associateResults.value = [];
        return;
    }
    associateTimer = setTimeout(async () => {
        try {
            const { data } = await window.axios.get(route('sms.received.account-search'), {
                params: { q: q.trim() },
            });
            associateResults.value = data.data || [];
        } catch {
            associateResults.value = [];
        }
    }, 250);
}

function selectAccount(account) {
    associateAccountId.value = account.id;
    associateLabel.value = account.label;
    associateQ.value = account.label;
    associateResults.value = [];
}

function submitAssociate() {
    if (!associateMessage.value || !associateAccountId.value || associateLoading.value) return;
    associateLoading.value = true;
    router.post(route('sms.received.associate', associateMessage.value.id), {
        account_id: associateAccountId.value,
    }, {
        preserveScroll: true,
        onFinish: () => { associateLoading.value = false; },
        onSuccess: () => closeAssociate(),
    });
}

function submitReply() {
    const body = (replyBody.value || '').trim();
    if (!replyMessage.value || !body || replyLoading.value) return;
    replyLoading.value = true;
    router.post(route('sms.received.reply', replyMessage.value.id), {
        message: body,
        runtime_device_id: replyDevice.value || undefined,
    }, {
        preserveScroll: true,
        onFinish: () => { replyLoading.value = false; },
        onSuccess: () => closeReply(),
    });
}

function ignoreMessage(row) {
    if (!row?.id || ignoreLoading.value[row.id]) return;
    if (!confirm('Ignore this SMS? It will be hidden from the dashboard unmatched list.')) return;
    ignoreLoading.value[row.id] = true;
    router.post(route('sms.received.ignore', row.id), {}, {
        preserveScroll: true,
        onFinish: () => { ignoreLoading.value[row.id] = false; },
    });
}

function deleteMessage(row) {
    if (!row?.id || deleteLoading.value[row.id]) return;
    if (!confirm('Permanently delete this received SMS record?')) return;
    deleteLoading.value[row.id] = true;
    router.delete(route('sms.received.destroy', row.id), {
        preserveScroll: true,
        onFinish: () => { deleteLoading.value[row.id] = false; },
    });
}
</script>

<template>
    <Head title="SMS Received" />
    <AppLayout>
        <template #header>SMS Received</template>
        <ListingPage
            title="SMS Received"
            :search="filters.search ?? ''"
            :can-export="can.export"
            :export-href="exportUrl()"
            @search="onSearch"
            @clear="onClear"
        >
            <template #filters>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Association</label>
                    <Select
                        :model-value="filters.association_status ?? null"
                        :options="filterOptions?.associationStatuses ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="(v) => applyFilter('association_status', v)"
                    />
                </div>
                <div class="min-w-[10rem]">
                    <label class="form-label block mb-1">Device</label>
                    <Select
                        :model-value="filters.device_id ?? null"
                        :options="filterOptions?.devices ?? []"
                        option-label="name"
                        option-value="id"
                        placeholder="All"
                        show-clear
                        class="w-full min-w-[10rem]"
                        @update:model-value="(v) => applyFilter('device_id', v)"
                    />
                </div>
            </template>

            <CollectimateDataTable
                :value="messages.data"
                :columns="columns"
                :rows="messages.per_page"
                :total-records="messages.total"
                :first="(messages.current_page - 1) * messages.per_page"
                :sort-field="filters.sort"
                :sort-order="filters.direction === 'asc' ? 1 : filters.direction === 'desc' ? -1 : null"
                @page="onPage"
                @sort="onSort"
            >
                <template #cell.received_at="{ row }">
                    {{ formatWhen(row.received_at) }}
                </template>
                <template #cell.message_preview="{ row }">
                    <span class="text-xs" style="color: var(--color-text-muted)">
                        {{ row.message_preview || '—' }}
                    </span>
                </template>
                <template #cell.account="{ row }">
                    <Link
                        v-if="row.account_id"
                        :href="route('accounts.show', row.account_id)"
                        class="underline"
                    >
                        {{ row.account_number || row.account_id }}
                    </Link>
                    <span v-else style="color: var(--color-text-muted)">—</span>
                </template>
                <template #cell.actions="{ row }">
                    <div class="flex flex-wrap gap-2 whitespace-nowrap">
                        <button type="button" class="underline text-sm" @click="openView(row)">View</button>
                        <button
                            v-if="can.associate && row.can_associate"
                            type="button"
                            class="underline text-sm"
                            @click="openAssociate(row)"
                        >Associate</button>
                        <button
                            v-if="can.reply && row.can_reply"
                            type="button"
                            class="underline text-sm"
                            @click="openReply(row)"
                        >Reply</button>
                        <button
                            v-if="can.associate && row.can_ignore"
                            type="button"
                            class="underline text-sm"
                            :disabled="ignoreLoading[row.id]"
                            @click="ignoreMessage(row)"
                        >{{ ignoreLoading[row.id] ? 'Ignoring…' : 'Ignore' }}</button>
                        <button
                            v-if="can.associate && row.can_delete"
                            type="button"
                            class="underline text-sm"
                            style="color: var(--color-danger, #9B3B3B)"
                            :disabled="deleteLoading[row.id]"
                            @click="deleteMessage(row)"
                        >{{ deleteLoading[row.id] ? 'Deleting…' : 'Delete' }}</button>
                    </div>
                </template>
            </CollectimateDataTable>
        </ListingPage>

        <Modal :show="!!viewMessage" max-width="2xl" @close="closeView">
            <div v-if="viewMessage" class="p-6 space-y-4">
                <h2 class="text-lg" style="font-weight: var(--font-weight-regular)">
                    Received SMS #{{ viewMessage.id }}
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="form-label mb-1">Sender</div>
                        <div>{{ viewMessage.sender || '—' }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Received</div>
                        <div>{{ formatWhen(viewMessage.received_at) }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Device</div>
                        <div>{{ viewMessage.device_id || '—' }}</div>
                    </div>
                    <div>
                        <div class="form-label mb-1">Association</div>
                        <div>{{ viewMessage.association_status || '—' }}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="form-label mb-1">Account</div>
                        <Link
                            v-if="viewMessage.account_id"
                            :href="route('accounts.show', viewMessage.account_id)"
                            class="underline"
                        >
                            {{ viewMessage.account_number || viewMessage.account_id }}
                            <span v-if="viewMessage.account_name"> — {{ viewMessage.account_name }}</span>
                        </Link>
                        <span v-else>—</span>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="form-label mb-1">Message</div>
                        <pre
                            class="whitespace-pre-wrap rounded border p-3 text-sm"
                            style="border-color: var(--color-border); background: var(--color-bg)"
                        >{{ viewMessage.message || '—' }}</pre>
                    </div>
                </div>
                <div class="flex justify-end">
                    <Button type="button" variant="outline" @click="closeView">Close</Button>
                </div>
            </div>
        </Modal>

        <Modal :show="!!associateMessage" max-width="lg" @close="closeAssociate">
            <div v-if="associateMessage" class="p-6 space-y-4">
                <h2 class="text-lg" style="font-weight: var(--font-weight-regular)">
                    Associate SMS #{{ associateMessage.id }}
                </h2>
                <p class="text-sm" style="color: var(--color-text-muted)">
                    From {{ associateMessage.sender }} · {{ associateMessage.message_preview }}
                </p>
                <div class="relative">
                    <label class="form-label block mb-1">Account</label>
                    <Input
                        :model-value="associateQ"
                        class="w-full"
                        placeholder="Search account number or name…"
                        @update:model-value="searchAccounts"
                    />
                    <div
                        v-if="associateResults.length"
                        class="absolute z-10 mt-1 w-full rounded border max-h-40 overflow-auto text-sm"
                        style="background: var(--color-bg-surface); border-color: var(--color-border)"
                    >
                        <button
                            v-for="account in associateResults"
                            :key="account.id"
                            type="button"
                            class="block w-full text-left px-3 py-1.5 hover:underline"
                            @click="selectAccount(account)"
                        >
                            {{ account.label }}
                        </button>
                    </div>
                    <p v-if="associateLabel" class="text-xs mt-1" style="color: var(--color-text-muted)">
                        Selected: {{ associateLabel }}
                    </p>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="closeAssociate">Cancel</Button>
                    <Button
                        type="button"
                        :disabled="!associateAccountId || associateLoading"
                        @click="submitAssociate"
                    >
                        {{ associateLoading ? 'Associating…' : 'Associate' }}
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal :show="!!replyMessage" max-width="lg" @close="closeReply">
            <div v-if="replyMessage" class="p-6 space-y-4">
                <h2 class="text-lg" style="font-weight: var(--font-weight-regular)">
                    Reply to SMS #{{ replyMessage.id }}
                </h2>
                <p class="text-sm" style="color: var(--color-text-muted)">
                    To {{ replyMessage.sender }}
                    <span v-if="replyMessage.account_number"> · {{ replyMessage.account_number }}</span>
                </p>
                <div>
                    <label class="form-label block mb-1">Message</label>
                    <textarea
                        v-model="replyBody"
                        rows="4"
                        class="w-full rounded border px-2 py-1.5 text-sm"
                        style="border-color: var(--color-border); background: var(--color-bg)"
                        placeholder="Reply message…"
                    />
                </div>
                <div>
                    <label class="form-label block mb-1">Device</label>
                    <select
                        v-model="replyDevice"
                        class="w-full rounded border px-2 py-1.5 text-sm"
                        style="border-color: var(--color-border); background: var(--color-bg)"
                    >
                        <option
                            v-for="device in replyDevices"
                            :key="device.id"
                            :value="device.id"
                        >
                            {{ device.name }}
                        </option>
                        <option
                            v-if="replyMessage.device_id && !replyDevices.some((d) => d.id === replyMessage.device_id)"
                            :value="replyMessage.device_id"
                        >
                            {{ replyMessage.device_id }} (inbound)
                        </option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="closeReply">Cancel</Button>
                    <Button
                        type="button"
                        :disabled="!(replyBody || '').trim() || replyLoading"
                        @click="submitReply"
                    >
                        {{ replyLoading ? 'Queuing…' : 'Queue reply' }}
                    </Button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
