<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SmsReceivedPanel from '@/Components/SmsReceivedPanel.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({ q: null }) },
    service: { type: Object, required: true },
    health_poll_seconds: { type: Number, default: 300 },
    list_poll_seconds: { type: Number, default: 8 },
    alert: { type: Object, default: () => ({}) },
    auto_device_recovery: { type: Boolean, default: true },
    devices_available: { type: Number, default: null },
    device_groups: { type: Array, default: () => [] },
    devices: { type: Array, default: () => [] },
    recent_batches: { type: Array, default: () => [] },
    received_sms: { type: Array, default: () => [] },
    reply_devices: { type: Array, default: () => [] },
    recovery_events: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

function clampHealthPollSeconds(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return 300;
    return Math.max(30, Math.min(3600, Math.floor(n)));
}

function clampListPollSeconds(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return 8;
    return Math.max(5, Math.min(300, Math.floor(n)));
}

const service = ref({ ...props.service });
const alert = ref({ ...props.alert });
const autoRecovery = ref(!!props.auto_device_recovery);
// Live count comes from health poll only — never overwrite from Inertia stubs (0/null).
const devicesAvailable = ref(
    props.devices_available != null && Number.isFinite(Number(props.devices_available))
        ? Number(props.devices_available)
        : null,
);
const deviceGroups = ref([...props.device_groups]);
const devices = ref([...props.devices]);
const recentBatches = ref([...props.recent_batches]);
const receivedSms = ref([...props.received_sms]);
const replyDevices = ref([...props.reply_devices]);
const recoveryEvents = ref([...props.recovery_events]);
const searchQ = ref(props.filters?.q ?? '');
const healthPollSeconds = ref(clampHealthPollSeconds(props.health_poll_seconds));
const listPollSeconds = ref(clampListPollSeconds(props.list_poll_seconds));
const healthCheckedAt = ref(null);
const healthCheckTick = ref(0);
const showTests = ref(false);
const probeDeviceId = ref('');
const probeResult = ref(null);
const probeLoading = ref(false);
const refreshHealthLoading = ref(false);
const refreshDevicesLoading = ref(false);

// Do NOT sync props.service or props.devices_available — live status comes from pollHealth only.
watch(() => props.health_poll_seconds, (v) => { healthPollSeconds.value = clampHealthPollSeconds(v); });
watch(() => props.list_poll_seconds, (v) => { listPollSeconds.value = clampListPollSeconds(v); });
watch(() => props.alert, (v) => { alert.value = { ...v }; }, { deep: true });
watch(() => props.auto_device_recovery, (v) => { autoRecovery.value = !!v; });
watch(() => props.device_groups, (v) => { deviceGroups.value = [...v]; }, { deep: true });
watch(() => props.devices, (v) => { devices.value = [...v]; }, { deep: true });
watch(() => props.recent_batches, (v) => { recentBatches.value = [...v]; }, { deep: true });
watch(() => props.received_sms, (v) => { receivedSms.value = [...v]; }, { deep: true });
watch(() => props.reply_devices, (v) => { replyDevices.value = [...v]; }, { deep: true });
watch(() => props.recovery_events, (v) => { recoveryEvents.value = [...v]; }, { deep: true });
watch(() => props.filters, (v) => { searchQ.value = v?.q ?? ''; }, { deep: true });

let snapshotTimer = null;
let healthTimer = null;
let healthTickTimer = null;
let snapshotInFlight = false;
let healthInFlight = false;

const uptimeLabel = computed(() => {
    const secs = service.value.uptime_seconds;
    if (secs == null) return '—';
    const h = Math.floor(secs / 3600);
    const m = Math.floor((secs % 3600) / 60);
    const s = Math.floor(secs % 60);
    if (h > 0) return `${h}h ${m}m ${s}s`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
});

const alertBanner = computed(() => {
    if (!alert.value?.code) return null;
    return alert.value.message || alert.value.code;
});

const alertIsWarning = computed(() => alert.value?.code === 'no_available_device');

const alertBannerStyle = computed(() => {
    if (alertIsWarning.value) {
        return { borderColor: '#b45309', color: '#b45309', background: '#fffbeb' };
    }
    return {
        borderColor: 'var(--color-danger, #b91c1c)',
        color: 'var(--color-danger, #b91c1c)',
        background: 'var(--color-bg-surface)',
    };
});

const runtimeDeviceOptions = computed(() =>
    devices.value
        .filter((d) => d.runtime_device_id)
        .map((d) => ({ id: d.runtime_device_id, label: `${d.runtime_device_id} (${d.name})` })),
);

/** Live list-devices count; 0 when service down; — until first health poll. */
const devicesAvailableDisplay = computed(() => {
    if (service.value.running === false) return 0;
    if (devicesAvailable.value == null) return '—';
    return devicesAvailable.value;
});

const serviceRunningLabel = computed(() => {
    if (service.value.running === true) return 'Running';
    if (service.value.running === false) return 'Down';
    return 'Checking…';
});

/** Traffic light: unknown=neutral, down=red, running+no devices=amber, running+devices=green */
const serviceStatusTone = computed(() => {
    if (service.value.running == null) return 'unknown';
    if (service.value.running === false) return 'danger';
    const n = devicesAvailable.value;
    if (n == null) return 'unknown';
    if (n <= 0) return 'warn';
    return 'ok';
});

const statusToneStyles = {
    unknown: {
        borderColor: 'var(--color-border)',
        color: 'var(--color-text-muted)',
        background: 'var(--color-bg-surface)',
    },
    danger: {
        borderColor: 'var(--color-danger, #b91c1c)',
        color: 'var(--color-danger, #b91c1c)',
        background: '#fef2f2',
    },
    warn: {
        borderColor: '#b45309',
        color: '#b45309',
        background: '#fffbeb',
    },
    ok: {
        borderColor: '#15803d',
        color: '#15803d',
        background: '#f0fdf4',
    },
};

const serviceCardStyle = computed(() => statusToneStyles[serviceStatusTone.value]);

const devicesCardStyle = computed(() => statusToneStyles[serviceStatusTone.value]);

const healthCheckedLabel = computed(() => {
    // Depend on tick so the label updates while the page is open.
    void healthCheckTick.value;
    if (!healthCheckedAt.value) return 'Not checked yet';
    const secs = Math.max(0, Math.floor((Date.now() - healthCheckedAt.value) / 1000));
    if (secs < 5) return 'Checked just now';
    if (secs < 60) return `Checked ${secs}s ago`;
    const mins = Math.floor(secs / 60);
    if (mins < 60) return `Checked ${mins}m ago`;
    return `Checked ${Math.floor(mins / 60)}h ago`;
});

function applySnapshot(data, { includeService = false } = {}) {
    if (includeService && data.service) {
        service.value = data.service;
        healthCheckedAt.value = Date.now();
    }
    if (data.health_poll_seconds != null) {
        healthPollSeconds.value = clampHealthPollSeconds(data.health_poll_seconds);
    }
    if (data.list_poll_seconds != null) {
        listPollSeconds.value = clampListPollSeconds(data.list_poll_seconds);
    }
    alert.value = data.alert;
    autoRecovery.value = !!data.auto_device_recovery;
    // Only health/check_service polls may update devices_available.
    if (includeService && data.devices_available != null) {
        devicesAvailable.value = Number(data.devices_available);
    }
    deviceGroups.value = data.device_groups ?? [];
    devices.value = data.devices ?? [];
    recentBatches.value = data.recent_batches ?? [];
    receivedSms.value = data.received_sms ?? [];
    replyDevices.value = data.reply_devices ?? replyDevices.value;
    recoveryEvents.value = data.recovery_events ?? [];
}

function deviceCardStyle(device) {
    if (device.is_healthy) {
        return {
            background: 'linear-gradient(135deg, #dcfce7 0%, #86efac 100%)',
            borderColor: '#16a34a',
        };
    }
    return {
        background: 'linear-gradient(135deg, #fee2e2 0%, #fca5a5 100%)',
        borderColor: '#dc2626',
    };
}

async function pollSnapshot() {
    if (snapshotInFlight) return;
    snapshotInFlight = true;
    try {
        const { data } = await window.axios.get(route('sms.poll'), {
            params: { q: searchQ.value || undefined },
        });
        applySnapshot(data, { includeService: false });
    } catch {
        // keep last good snapshot
    } finally {
        snapshotInFlight = false;
    }
}

async function pollHealth() {
    if (healthInFlight) return;
    healthInFlight = true;
    try {
        const { data } = await window.axios.get(route('sms.poll'), {
            params: {
                q: searchQ.value || undefined,
                check_service: 1,
            },
        });
        applySnapshot(data, { includeService: true });
    } catch {
        // keep last good service state
    } finally {
        healthInFlight = false;
    }
}

function restartHealthTimer() {
    if (healthTimer) clearInterval(healthTimer);
    const ms = clampHealthPollSeconds(healthPollSeconds.value) * 1000;
    healthTimer = setInterval(pollHealth, ms);
}

function restartSnapshotTimer() {
    if (snapshotTimer) clearInterval(snapshotTimer);
    const ms = clampListPollSeconds(listPollSeconds.value) * 1000;
    snapshotTimer = setInterval(pollSnapshot, ms);
}

onMounted(() => {
    pollHealth();
    pollSnapshot();
    restartSnapshotTimer();
    restartHealthTimer();
    healthTickTimer = setInterval(() => {
        healthCheckTick.value += 1;
    }, 1000);
});

onUnmounted(() => {
    if (snapshotTimer) clearInterval(snapshotTimer);
    if (healthTimer) clearInterval(healthTimer);
    if (healthTickTimer) clearInterval(healthTickTimer);
});

watch(healthPollSeconds, () => {
    restartHealthTimer();
});

watch(listPollSeconds, () => {
    restartSnapshotTimer();
});

function afterDashboardAction({ checkService = false } = {}) {
    return {
        preserveScroll: true,
        onSuccess: () => {
            pollSnapshot();
            if (checkService) {
                pollHealth();
            }
        },
    };
}

function applySearch() {
    router.get(route('sms.dashboard'), { q: searchQ.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            pollSnapshot();
        },
    });
}

function restartService() {
    if (!confirm('Restart the SMS service process?')) return;
    router.post(route('sms.service.restart'), {}, afterDashboardAction({ checkService: true }));
}
function refreshHealth() {
    if (refreshHealthLoading.value) return;
    refreshHealthLoading.value = true;
    router.post(route('sms.health.refresh'), {}, {
        ...afterDashboardAction({ checkService: true }),
        onFinish: () => {
            refreshHealthLoading.value = false;
        },
    });
}
function refreshDevices() {
    if (refreshDevicesLoading.value) return;
    refreshDevicesLoading.value = true;
    router.post(route('sms.devices.refresh'), {}, {
        ...afterDashboardAction({ checkService: true }),
        onFinish: () => {
            refreshDevicesLoading.value = false;
        },
    });
}
function dispatchTick() {
    router.post(route('sms.dispatch'), {}, afterDashboardAction({ checkService: true }));
}
function onAutoRecoveryChange(value) {
    const next = !!value;
    if (next === autoRecovery.value) return;
    autoRecovery.value = next;
    router.post(route('sms.auto-recovery'), {
        auto_device_recovery: next,
    }, afterDashboardAction());
}
function cancelBatch(batch) {
    if (!confirm(`Cancel remaining queued items in batch #${batch.id}? Linked SMS Send activities will be deleted.`)) return;
    router.post(route('sms.batches.cancel', batch.id), {}, afterDashboardAction());
}
function pauseBatch(batch) {
    router.post(route('sms.batches.pause', batch.id), {}, afterDashboardAction());
}
function resumeBatch(batch) {
    router.post(route('sms.batches.resume', batch.id), {}, afterDashboardAction());
}
function bumpPriority(batch, direction) {
    router.post(route('sms.batches.priority', batch.id), { direction }, afterDashboardAction());
}
function deviceAction(action, deviceId) {
    if (!deviceId) return;
    const routes = {
        restart: 'sms.runtime-devices.restart',
        start: 'sms.runtime-devices.start',
        delete: 'sms.runtime-devices.delete',
    };
    if (action === 'delete' && !confirm(`Remove runtime device ${deviceId}?`)) return;
    router.post(route(routes[action]), { device_id: deviceId }, afterDashboardAction({ checkService: true }));
}

async function runProbe(action) {
    probeLoading.value = true;
    probeResult.value = null;
    try {
        const { data } = await window.axios.post(route('sms.probe'), {
            action,
            device_id: probeDeviceId.value || undefined,
        });
        probeResult.value = data;
        if (data?.ok && (action === 'health' || action === 'devices')) {
            await pollHealth();
            await pollSnapshot();
        }
    } catch (e) {
        probeResult.value = e?.response?.data || { ok: false, error: e.message };
    } finally {
        probeLoading.value = false;
    }
}
</script>

<template>
    <Head title="SMS Dashboard" />
    <AppLayout>
        <template #header>SMS Dashboard</template>

        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="page-title">SMS Dashboard</h1>
                    <p class="mt-1 text-sm" style="color: var(--color-text-muted)">
                        Device ops, batch queue, and endpoint tests. Start the SMS service executable outside Laravel.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.manage"
                        :href="route('sms.config')"
                        class="inline-flex items-center rounded border px-3 py-1.5 text-sm"
                        style="border-color: var(--color-border)"
                    >
                        SMS Configuration
                    </Link>
                    <Button v-if="can.manage" type="button" variant="outline" @click="restartService">Restart process</Button>
                    <Button
                        v-if="can.manage"
                        type="button"
                        variant="outline"
                        :disabled="refreshHealthLoading"
                        @click="refreshHealth"
                    >
                        {{ refreshHealthLoading ? 'Refreshing…' : 'Refresh health' }}
                    </Button>
                    <Button
                        v-if="can.manage"
                        type="button"
                        variant="outline"
                        :disabled="refreshDevicesLoading"
                        @click="refreshDevices"
                    >
                        {{ refreshDevicesLoading ? 'Refreshing…' : 'Refresh devices available' }}
                    </Button>
                    <Button v-if="can.manage" type="button" variant="outline" @click="dispatchTick">Dispatch now</Button>
                </div>
            </div>

            <div v-if="alertBanner" class="rounded border px-4 py-3 text-sm" :style="alertBannerStyle">
                {{ alertBanner }}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded border p-4" :style="serviceCardStyle">
                    <div class="text-xs" style="opacity: 0.85">Service</div>
                    <div class="mt-1 text-lg font-semibold">{{ serviceRunningLabel }}</div>
                    <div class="text-xs mt-1" style="opacity: 0.85">
                        Health: {{ service.health?.status || (service.running === true ? 'OK' : '—') }}
                    </div>
                    <div class="text-xs mt-1" style="opacity: 0.75">
                        {{ healthCheckedLabel }} · every {{ healthPollSeconds }}s
                    </div>
                </div>
                <div class="rounded border p-4" :style="devicesCardStyle">
                    <div class="text-xs" style="opacity: 0.85">Devices available</div>
                    <div class="mt-1 text-lg font-semibold">{{ devicesAvailableDisplay }}</div>
                </div>
                <div class="rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="text-xs" style="color: var(--color-text-muted)">Uptime (session)</div>
                    <div class="mt-1 text-lg font-semibold">{{ uptimeLabel }}</div>
                </div>
                <div class="rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="text-xs" style="color: var(--color-text-muted)">SMS sent since start</div>
                    <div class="mt-1 text-lg font-semibold">{{ service.sms_sent_since_start ?? 0 }}</div>
                </div>
            </div>

            <section
                v-if="can.manage"
                class="rounded border p-4 space-y-2"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :checked="autoRecovery" @update:checked="onAutoRecoveryChange" />
                    <span>Automatic device recovery (RestartDevice / DeleteDevice callbacks)</span>
                </label>
                <div class="text-xs space-y-1" style="color: var(--color-text-muted)">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-medium text-sm" style="color: inherit">Recent recovery events</div>
                        <Link :href="route('sms.callbacks.index')" class="underline">
                            All SMS callbacks
                        </Link>
                    </div>
                    <template v-if="recoveryEvents.length">
                        <div v-for="ev in recoveryEvents" :key="ev.id">
                            #{{ ev.id }} {{ ev.event_type }} / {{ ev.response_type || '—' }} · {{ ev.device_id || '—' }}
                        </div>
                    </template>
                    <div v-else>No recent recovery events.</div>
                </div>
            </section>

            <section class="rounded border p-4 space-y-3" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 class="text-sm font-semibold">Queue search</h2>
                    <form class="flex gap-2" @submit.prevent="applySearch">
                        <Input v-model="searchQ" class="w-64" placeholder="Batch id, phone, message, account…" />
                        <Button type="submit" variant="outline">Search</Button>
                    </form>
                </div>
            </section>

            <div class="space-y-1">
                <p class="text-xs" style="color: var(--color-text-muted)">
                    Pending batches and unmatched SMS auto-refresh every {{ listPollSeconds }}s
                </p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">
                <section class="rounded border p-3 space-y-2" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">Pending batches</h2>
                        <Link :href="route('sms.batches.index')" class="text-xs underline">
                            View all
                        </Link>
                    </div>
                    <div v-if="!recentBatches.length" class="text-xs" style="color: var(--color-text-muted)">No pending batches.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left" style="color: var(--color-text-muted)">
                                    <th class="py-0.5 pr-2">Batch</th>
                                    <th class="py-0.5 pr-2">Status</th>
                                    <th class="py-0.5 pr-2">Prio</th>
                                    <th class="py-0.5 pr-2">Queued</th>
                                    <th class="py-0.5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="batch in recentBatches" :key="batch.id" class="border-t" style="border-color: var(--color-border)">
                                    <td class="py-0.5 pr-2 max-w-[8rem]">
                                        <Link :href="route('sms.batches.show', batch.id)" class="underline font-medium">
                                            {{ batch.id }}
                                        </Link>
                                        <div class="truncate" style="color: var(--color-text-muted)">{{ batch.message_body || '—' }}</div>
                                    </td>
                                    <td
                                        class="py-0.5 pr-2"
                                        :style="batch.status === 'cancelled' ? { color: '#b91c1c' } : undefined"
                                    >{{ batch.status }}</td>
                                    <td class="py-0.5 pr-2">{{ batch.priority }}</td>
                                    <td class="py-0.5 pr-2">{{ batch.queued }}</td>
                                    <td class="py-0.5 space-x-1.5 whitespace-nowrap">
                                        <button
                                            v-if="can.manage && batch.status !== 'paused' && batch.queued > 0"
                                            type="button"
                                            class="underline"
                                            @click="pauseBatch(batch)"
                                        >Pause</button>
                                        <button
                                            v-if="can.manage && batch.status === 'paused'"
                                            type="button"
                                            class="underline"
                                            @click="resumeBatch(batch)"
                                        >Resume</button>
                                        <button
                                            v-if="can.manage && batch.queued > 0"
                                            type="button"
                                            class="underline"
                                            @click="bumpPriority(batch, 'up')"
                                        >↑</button>
                                        <button
                                            v-if="can.manage && batch.queued > 0"
                                            type="button"
                                            class="underline"
                                            @click="bumpPriority(batch, 'down')"
                                        >↓</button>
                                        <button
                                            v-if="can.cancel && batch.queued > 0"
                                            type="button"
                                            class="underline"
                                            @click="cancelBatch(batch)"
                                        >Cancel</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <SmsReceivedPanel
                    :messages="receivedSms"
                    :reply-devices="replyDevices"
                    :can="can"
                    compact
                    show-view-all
                    @changed="() => { pollSnapshot(); }"
                />
            </div>
            </div>

            <section class="space-y-4">
                <h2 class="text-sm font-semibold">SMS device groups</h2>
                <div v-if="!deviceGroups.length" class="text-sm rounded border p-4" style="color: var(--color-text-muted); border-color: var(--color-border)">
                    No device groups yet. Create groups in SMS Configuration.
                </div>
                <div
                    v-for="group in deviceGroups"
                    :key="group.id ?? 'ungrouped'"
                    class="rounded border p-3 space-y-2"
                    style="background: var(--color-bg-surface); border-color: var(--color-border)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-semibold">
                            {{ group.name }}
                            <span class="font-normal" style="color: var(--color-text-muted)">({{ group.device_count }})</span>
                            <span v-if="!group.enabled" class="ml-2 text-xs font-normal" style="color: var(--color-text-muted)">Disabled</span>
                        </div>
                    </div>
                    <div v-if="!group.devices?.length" class="text-xs" style="color: var(--color-text-muted)">No devices</div>
                    <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2">
                        <div
                            v-for="device in group.devices"
                            :key="device.id"
                            class="rounded border px-2 py-2 text-xs space-y-1"
                            :style="deviceCardStyle(device)"
                        >
                            <div class="flex items-start justify-between gap-1">
                                <div class="font-semibold leading-tight truncate" :title="device.name">{{ device.name }}</div>
                                <span class="shrink-0 opacity-80">{{ device.type }}</span>
                            </div>
                            <div class="truncate opacity-80" :title="device.runtime_device_id || ''">
                                {{ device.runtime_device_id || 'No runtime ID' }}
                            </div>
                            <div class="flex gap-2 font-medium">
                                <span>Sent {{ device.sent_count ?? 0 }}</span>
                                <span>Failed {{ device.failed_count ?? 0 }}</span>
                            </div>
                            <div class="opacity-80 truncate">
                                {{ device.health || '—' }} · {{ device.state || '—' }}
                                · {{ device.sending ? 'Texting…' : 'Idle' }}
                            </div>
                            <div v-if="can.manage && device.runtime_device_id" class="flex flex-wrap gap-1">
                                <Button type="button" size="sm" variant="outline" class="h-6 px-1.5 text-[10px]" @click="deviceAction('restart', device.runtime_device_id)">Restart</Button>
                                <Button type="button" size="sm" variant="outline" class="h-6 px-1.5 text-[10px]" @click="deviceAction('start', device.runtime_device_id)">Start</Button>
                                <Button type="button" size="sm" variant="outline" class="h-6 px-1.5 text-[10px]" @click="deviceAction('delete', device.runtime_device_id)">Del</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="can.manage"
                class="rounded border p-4 space-y-3"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Test endpoints</h2>
                    <Button type="button" size="sm" variant="outline" @click="showTests = !showTests">
                        {{ showTests ? 'Hide' : 'Show' }}
                    </Button>
                </div>
                <div v-if="showTests" class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" size="sm" variant="outline" :disabled="probeLoading" @click="runProbe('ping')">Ping</Button>
                        <Button type="button" size="sm" variant="outline" :disabled="probeLoading" @click="runProbe('health')">Health</Button>
                        <Button type="button" size="sm" variant="outline" :disabled="probeLoading" @click="runProbe('info')">Info</Button>
                        <Button type="button" size="sm" variant="outline" :disabled="probeLoading" @click="runProbe('devices')">List devices</Button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="form-label block mb-1">Device id</label>
                            <Input v-model="probeDeviceId" class="w-56" placeholder="AT-COM3" list="sms-runtime-devices" />
                            <datalist id="sms-runtime-devices">
                                <option v-for="opt in runtimeDeviceOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                            </datalist>
                        </div>
                        <Button type="button" size="sm" variant="outline" :disabled="probeLoading || !probeDeviceId" @click="runProbe('restart')">Restart</Button>
                        <Button type="button" size="sm" variant="outline" :disabled="probeLoading || !probeDeviceId" @click="runProbe('start')">Start</Button>
                        <Button type="button" size="sm" variant="outline" :disabled="probeLoading || !probeDeviceId" @click="runProbe('delete')">Delete</Button>
                    </div>
                    <pre
                        v-if="probeResult"
                        class="text-xs overflow-x-auto rounded border p-3 max-h-64"
                        style="border-color: var(--color-border); background: var(--color-bg)"
                    >{{ JSON.stringify(probeResult, null, 2) }}</pre>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
