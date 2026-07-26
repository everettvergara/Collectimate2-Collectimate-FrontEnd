<script setup>
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    replyDevices: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false },
    showViewAll: { type: Boolean, default: false },
});

const emit = defineEmits(['changed']);

const associateDraft = ref({});
const associateResults = ref({});
const associateLoading = ref({});
const replyDraft = ref({});
const replyDevice = ref({});
const replyLoading = ref({});
const ignoreLoading = ref({});
const deleteLoading = ref({});
const searchTimers = {};

function afterChange() {
    emit('changed');
}

function ignoreMessage(message) {
    if (!message?.id || ignoreLoading.value[message.id]) return;
    if (!confirm('Ignore this SMS? It will be hidden from the dashboard unmatched list (e.g. network promo).')) return;
    ignoreLoading.value[message.id] = true;
    router.post(route('sms.received.ignore', message.id), {}, {
        preserveScroll: true,
        onFinish: () => { ignoreLoading.value[message.id] = false; },
        onSuccess: () => afterChange(),
    });
}

function deleteMessage(message) {
    if (!message?.id || deleteLoading.value[message.id]) return;
    if (!confirm('Permanently delete this received SMS record?')) return;
    deleteLoading.value[message.id] = true;
    router.delete(route('sms.received.destroy', message.id), {
        preserveScroll: true,
        onFinish: () => { deleteLoading.value[message.id] = false; },
        onSuccess: () => afterChange(),
    });
}

async function searchAccounts(messageId, q) {
    const query = (q || '').trim();
    associateDraft.value[messageId] = {
        ...(associateDraft.value[messageId] || {}),
        q,
    };
    if (searchTimers[messageId]) clearTimeout(searchTimers[messageId]);
    if (query.length < 1) {
        associateResults.value[messageId] = [];
        return;
    }
    searchTimers[messageId] = setTimeout(async () => {
        try {
            const { data } = await window.axios.get(route('sms.received.account-search'), {
                params: { q: query },
            });
            associateResults.value[messageId] = data.data || [];
        } catch {
            associateResults.value[messageId] = [];
        }
    }, 250);
}

function selectAccount(messageId, account) {
    associateDraft.value[messageId] = {
        ...(associateDraft.value[messageId] || {}),
        account_id: account.id,
        label: account.label,
        q: account.label,
    };
    associateResults.value[messageId] = [];
}

function associate(message) {
    const draft = associateDraft.value[message.id] || {};
    if (!draft.account_id || associateLoading.value[message.id]) return;
    associateLoading.value[message.id] = true;
    router.post(route('sms.received.associate', message.id), {
        account_id: draft.account_id,
    }, {
        preserveScroll: true,
        onFinish: () => {
            associateLoading.value[message.id] = false;
        },
        onSuccess: () => {
            associateDraft.value[message.id] = {};
            afterChange();
        },
    });
}

function ensureReplyDefaults(message) {
    if (!replyDevice.value[message.id]) {
        replyDevice.value[message.id] = message.device_id || props.replyDevices[0]?.id || '';
    }
    if (replyDraft.value[message.id] == null) {
        replyDraft.value[message.id] = '';
    }
}

function reply(message) {
    ensureReplyDefaults(message);
    const body = (replyDraft.value[message.id] || '').trim();
    if (!body || replyLoading.value[message.id]) return;
    replyLoading.value[message.id] = true;
    router.post(route('sms.received.reply', message.id), {
        message: body,
        runtime_device_id: replyDevice.value[message.id] || undefined,
    }, {
        preserveScroll: true,
        onFinish: () => {
            replyLoading.value[message.id] = false;
        },
        onSuccess: () => {
            replyDraft.value[message.id] = '';
            afterChange();
        },
    });
}
</script>

<template>
    <section
        class="rounded border p-4 space-y-3"
        style="background: var(--color-bg-surface); border-color: var(--color-border)"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ compact ? 'Unmatched SMS' : 'Received SMS' }}</h2>
            <Link
                v-if="showViewAll"
                :href="route('sms.received.index', { association_status: 'unmatched' })"
                class="text-sm underline"
            >
                View all
            </Link>
        </div>

        <div v-if="!messages.length" class="text-sm" style="color: var(--color-text-muted)">
            {{ compact ? 'No unmatched SMS.' : 'No received SMS yet.' }}
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="message in messages"
                :key="message.id"
                class="rounded border p-3 space-y-2 text-sm"
                style="border-color: var(--color-border)"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-medium">{{ message.sender }}</div>
                        <div
                            class="text-xs mt-0.5"
                            :class="compact ? 'truncate max-w-[18rem]' : ''"
                            style="color: var(--color-text-muted)"
                        >
                            {{ message.message_preview || message.message || '—' }}
                        </div>
                    </div>
                    <div class="text-xs text-right" style="color: var(--color-text-muted)">
                        <div>{{ message.device_id || '—' }}</div>
                        <div>{{ message.received_at ? new Date(message.received_at).toLocaleString() : '—' }}</div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span
                        v-if="!compact || message.association_status !== 'unmatched'"
                        class="rounded px-1.5 py-0.5 border"
                        style="border-color: var(--color-border)"
                    >
                        {{ message.association_status }}
                    </span>
                    <Link
                        v-if="message.account_id"
                        :href="route('accounts.show', message.account_id)"
                        class="underline"
                    >
                        {{ message.account_number || message.account_id }}
                        <span v-if="message.account_name"> — {{ message.account_name }}</span>
                    </Link>
                    <span
                        v-else-if="!compact"
                        style="color: var(--color-text-muted)"
                    >
                        No account
                    </span>
                    <span v-if="message.account_activity_id" style="color: var(--color-text-muted)">
                        Activity {{ message.account_activity_id }}
                    </span>
                    <button
                        v-if="can.associate && message.can_ignore"
                        type="button"
                        class="underline"
                        :disabled="ignoreLoading[message.id]"
                        @click="ignoreMessage(message)"
                    >
                        {{ ignoreLoading[message.id] ? 'Ignoring…' : 'Ignore' }}
                    </button>
                    <button
                        v-if="can.associate && message.can_delete"
                        type="button"
                        class="underline"
                        style="color: #b91c1c"
                        :disabled="deleteLoading[message.id]"
                        @click="deleteMessage(message)"
                    >
                        {{ deleteLoading[message.id] ? 'Deleting…' : 'Delete' }}
                    </button>
                </div>

                <div v-if="can.associate && message.can_associate" class="space-y-1">
                    <div class="relative">
                        <Input
                            :model-value="associateDraft[message.id]?.q || ''"
                            class="w-full"
                            placeholder="Search account to associate…"
                            @update:model-value="(v) => searchAccounts(message.id, v)"
                        />
                        <div
                            v-if="(associateResults[message.id] || []).length"
                            class="absolute z-10 mt-1 w-full rounded border max-h-40 overflow-auto text-sm"
                            style="background: var(--color-bg-surface); border-color: var(--color-border)"
                        >
                            <button
                                v-for="account in associateResults[message.id]"
                                :key="account.id"
                                type="button"
                                class="block w-full text-left px-3 py-1.5 hover:underline"
                                @click="selectAccount(message.id, account)"
                            >
                                {{ account.label }}
                            </button>
                        </div>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="!associateDraft[message.id]?.account_id || associateLoading[message.id]"
                        @click="associate(message)"
                    >
                        {{ associateLoading[message.id] ? 'Associating…' : 'Associate' }}
                    </Button>
                </div>

                <div v-if="can.reply && message.can_reply" class="space-y-1" @focusin="ensureReplyDefaults(message)">
                    <textarea
                        v-model="replyDraft[message.id]"
                        rows="2"
                        class="w-full rounded border px-2 py-1.5 text-sm"
                        style="border-color: var(--color-border); background: var(--color-bg)"
                        placeholder="Reply message…"
                        @focus="ensureReplyDefaults(message)"
                    />
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="replyDevice[message.id]"
                            class="rounded border px-2 py-1 text-sm min-w-[12rem]"
                            style="border-color: var(--color-border); background: var(--color-bg)"
                            @focus="ensureReplyDefaults(message)"
                        >
                            <option
                                v-for="device in replyDevices"
                                :key="device.id"
                                :value="device.id"
                            >
                                {{ device.name }}
                            </option>
                            <option
                                v-if="message.device_id && !replyDevices.some((d) => d.id === message.device_id)"
                                :value="message.device_id"
                            >
                                {{ message.device_id }} (inbound)
                            </option>
                        </select>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="!(replyDraft[message.id] || '').trim() || replyLoading[message.id]"
                            @click="reply(message)"
                        >
                            {{ replyLoading[message.id] ? 'Queuing…' : 'Reply' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
