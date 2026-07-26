<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    defaults: {
        type: Object,
        default: () => ({}),
    },
    service: {
        type: Object,
        default: () => ({ running: false, error: null, health: null }),
    },
    deviceGroups: { type: Array, default: () => [] },
    devices: { type: Array, default: () => [] },
    deviceTypes: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

/** Instruction defaults — fill empty values and drive placeholders. */
const D = {
    service_base_url: props.defaults.service_base_url || 'http://127.0.0.1:8080/api/v1',
    api_key: props.defaults.api_key || 'xxxxxxxx',
    callback_base_url: props.defaults.callback_base_url || 'http://127.0.0.1:8000',
    service_exe_path: props.defaults.service_exe_path
        || 'C:\\collectimate\\collectimate_sms_service\\bin\\collectimate_sms_service.exe',
    config_json_path: props.defaults.config_json_path
        || 'C:\\collectimate\\collectimate_sms_service\\config\\config.json',
    dashboard_health_poll_seconds: props.defaults.dashboard_health_poll_seconds ?? 300,
    dashboard_list_poll_seconds: props.defaults.dashboard_list_poll_seconds ?? 8,
    http_ports_to_test: (props.defaults.http_ports_to_test || [8080]).join(', '),
    config_service: {
        service_id: 'sms-service-01',
        friendly_name: 'Primary SMS Gateway',
        listen_address: '0.0.0.0',
        listen_port: 8080,
        timezone: 'Asia/Manila',
        shutdown_timeout_seconds: 15,
        auto_detect_at_devices: true,
        ...(props.defaults.config_service || {}),
    },
    config_logging: {
        level: 'info',
        directory: 'logs',
        max_file_size_mb: 20,
        max_files: 10,
        console: true,
        ...(props.defaults.config_logging || {}),
    },
    config_http: {
        request_timeout_seconds: 60,
        worker_threads: 4,
        keep_alive: true,
        ...(props.defaults.config_http || {}),
    },
    config_callbacks: {
        base_url: 'http://127.0.0.1:8000/api/sms/callback',
        api_key: 'xxxxxxxx',
        retry_attempts: 5,
        retry_delay_seconds: 5,
        connect_timeout_seconds: 10,
        request_timeout_seconds: 20,
        ...(props.defaults.config_callbacks || {}),
    },
    config_queue: {
        max_size: 100000,
        worker_threads: 2,
        ...(props.defaults.config_queue || {}),
    },
};

function pick(value, fallback) {
    if (value === null || value === undefined || value === '') return fallback;
    return value;
}

function pickBool(value, fallback) {
    if (value === null || value === undefined) return fallback;
    return !!value;
}

const svc = props.settings.config_service ?? {};
const log = props.settings.config_logging ?? {};
const http = props.settings.config_http ?? {};
const cb = props.settings.config_callbacks ?? {};
const queue = props.settings.config_queue ?? {};

const form = useForm({
    service_base_url: pick(props.settings.service_base_url, D.service_base_url),
    api_key: pick(props.settings.api_key || cb.api_key, D.api_key),
    callback_base_url: pick(props.settings.callback_base_url, D.callback_base_url),
    service_exe_path: pick(props.settings.service_exe_path, D.service_exe_path),
    config_json_path: pick(props.settings.config_json_path, D.config_json_path),
    auto_detect_at_ports: pickBool(props.settings.auto_detect_at_ports, true),
    dashboard_health_poll_seconds: pick(
        props.settings.dashboard_health_poll_seconds,
        D.dashboard_health_poll_seconds,
    ),
    dashboard_list_poll_seconds: pick(
        props.settings.dashboard_list_poll_seconds,
        D.dashboard_list_poll_seconds,
    ),
    http_ports_to_test: (props.settings.http_ports_to_test?.length
        ? props.settings.http_ports_to_test
        : [D.config_service.listen_port]).join(', '),
    sync_config_json: true,
    config_service: {
        service_id: pick(svc.service_id, D.config_service.service_id),
        friendly_name: pick(svc.friendly_name, D.config_service.friendly_name),
        listen_address: pick(svc.listen_address, D.config_service.listen_address),
        listen_port: pick(svc.listen_port, D.config_service.listen_port),
        timezone: pick(svc.timezone, D.config_service.timezone),
        shutdown_timeout_seconds: pick(svc.shutdown_timeout_seconds, D.config_service.shutdown_timeout_seconds),
        auto_detect_at_devices: pickBool(svc.auto_detect_at_devices, D.config_service.auto_detect_at_devices),
    },
    config_logging: {
        level: pick(log.level, D.config_logging.level),
        directory: pick(log.directory, D.config_logging.directory),
        max_file_size_mb: pick(log.max_file_size_mb, D.config_logging.max_file_size_mb),
        max_files: pick(log.max_files, D.config_logging.max_files),
        console: pickBool(log.console, D.config_logging.console),
    },
    config_http: {
        request_timeout_seconds: pick(http.request_timeout_seconds, D.config_http.request_timeout_seconds),
        worker_threads: pick(http.worker_threads, D.config_http.worker_threads),
        keep_alive: pickBool(http.keep_alive, D.config_http.keep_alive),
    },
    config_callbacks: {
        base_url: pick(cb.base_url || props.settings.resolved_callback_url, D.config_callbacks.base_url),
        api_key: pick(cb.api_key || props.settings.api_key, D.config_callbacks.api_key),
        retry_attempts: pick(cb.retry_attempts, D.config_callbacks.retry_attempts),
        retry_delay_seconds: pick(cb.retry_delay_seconds, D.config_callbacks.retry_delay_seconds),
        connect_timeout_seconds: pick(cb.connect_timeout_seconds, D.config_callbacks.connect_timeout_seconds),
        request_timeout_seconds: pick(cb.request_timeout_seconds, D.config_callbacks.request_timeout_seconds),
    },
    config_queue: {
        max_size: pick(queue.max_size, D.config_queue.max_size),
        worker_threads: pick(queue.worker_threads, D.config_queue.worker_threads),
    },
});

watch(
    () => form.config_service.listen_port,
    (port) => {
        if (port) {
            form.http_ports_to_test = String(port);
        }
    },
);

watch(
    () => form.auto_detect_at_ports,
    (v) => {
        form.config_service.auto_detect_at_devices = !!v;
    },
);

watch(
    () => form.config_callbacks.api_key,
    (v) => {
        form.api_key = v ?? '';
    },
);

const portTestResults = ref([]);
const portTestLoading = ref(false);
const portTestError = ref(null);

const showDeviceModal = ref(false);
const editingDevice = ref(null);
const showGroupModal = ref(false);
const editingGroup = ref(null);

const deviceForm = useForm({
    sms_device_group_id: null,
    type: 'AT',
    name: '',
    enabled: true,
    sort_order: 0,
    runtime_device_id: '',
    config: {
        port: '',
        baud_rate: 115200,
        demo_send_success_rate: 0.99,
        demo_receive_interval_seconds: 300,
    },
});

const groupForm = useForm({
    name: '',
    enabled: true,
    sort_order: 0,
});

const deviceTypeOptions = computed(() => props.deviceTypes ?? []);
const deviceGroupOptions = computed(() =>
    (props.deviceGroups ?? []).map((g) => ({ id: g.id, name: g.name })),
);
const isDemoDevice = computed(() => deviceForm.type === 'Demo');
const devicesByGroup = computed(() => {
    const groups = props.deviceGroups ?? [];
    const devices = props.devices ?? [];
    return groups.map((g) => ({
        ...g,
        devices: devices.filter((d) => d.sms_device_group_id === g.id),
    }));
});

function saveSettings() {
    const ports = String(form.http_ports_to_test || '')
        .split(/[,\s]+/)
        .map((p) => parseInt(p, 10))
        .filter((p) => !Number.isNaN(p) && p > 0);

    form
        .transform((data) => {
            const listenPort = Number(pick(data.config_service.listen_port, D.config_service.listen_port)) || 8080;
            return {
                ...data,
                service_base_url: pick(data.service_base_url, D.service_base_url),
                config_json_path: pick(data.config_json_path, D.config_json_path),
                service_exe_path: pick(data.service_exe_path, D.service_exe_path),
                dashboard_health_poll_seconds: Math.max(
                    30,
                    Math.min(
                        3600,
                        Number(pick(data.dashboard_health_poll_seconds, D.dashboard_health_poll_seconds)) || 300,
                    ),
                ),
                dashboard_list_poll_seconds: Math.max(
                    5,
                    Math.min(
                        300,
                        Number(pick(data.dashboard_list_poll_seconds, D.dashboard_list_poll_seconds)) || 8,
                    ),
                ),
                http_ports_to_test: ports.length ? ports : [listenPort],
                api_key: pick(data.config_callbacks.api_key, D.config_callbacks.api_key),
                auto_detect_at_ports: pickBool(data.config_service.auto_detect_at_devices, true),
                config_service: {
                    ...D.config_service,
                    ...data.config_service,
                    service_id: pick(data.config_service.service_id, D.config_service.service_id),
                    friendly_name: pick(data.config_service.friendly_name, D.config_service.friendly_name),
                    listen_address: pick(data.config_service.listen_address, D.config_service.listen_address),
                    listen_port: listenPort,
                    timezone: pick(data.config_service.timezone, D.config_service.timezone),
                    shutdown_timeout_seconds: pick(
                        data.config_service.shutdown_timeout_seconds,
                        D.config_service.shutdown_timeout_seconds,
                    ),
                },
                config_logging: {
                    ...D.config_logging,
                    ...data.config_logging,
                    level: pick(data.config_logging.level, D.config_logging.level),
                    directory: pick(data.config_logging.directory, D.config_logging.directory),
                    max_file_size_mb: pick(data.config_logging.max_file_size_mb, D.config_logging.max_file_size_mb),
                    max_files: pick(data.config_logging.max_files, D.config_logging.max_files),
                },
                config_http: {
                    ...D.config_http,
                    ...data.config_http,
                    request_timeout_seconds: pick(
                        data.config_http.request_timeout_seconds,
                        D.config_http.request_timeout_seconds,
                    ),
                    worker_threads: pick(data.config_http.worker_threads, D.config_http.worker_threads),
                },
                config_callbacks: {
                    ...D.config_callbacks,
                    ...data.config_callbacks,
                    base_url: pick(data.config_callbacks.base_url, D.config_callbacks.base_url),
                    api_key: pick(data.config_callbacks.api_key, D.config_callbacks.api_key),
                    retry_attempts: pick(data.config_callbacks.retry_attempts, D.config_callbacks.retry_attempts),
                    retry_delay_seconds: pick(
                        data.config_callbacks.retry_delay_seconds,
                        D.config_callbacks.retry_delay_seconds,
                    ),
                    connect_timeout_seconds: pick(
                        data.config_callbacks.connect_timeout_seconds,
                        D.config_callbacks.connect_timeout_seconds,
                    ),
                    request_timeout_seconds: pick(
                        data.config_callbacks.request_timeout_seconds,
                        D.config_callbacks.request_timeout_seconds,
                    ),
                },
                config_queue: {
                    ...D.config_queue,
                    ...data.config_queue,
                    max_size: pick(data.config_queue.max_size, D.config_queue.max_size),
                    worker_threads: pick(data.config_queue.worker_threads, D.config_queue.worker_threads),
                },
            };
        })
        .put(route('sms.config.update'), { preserveScroll: true });
}

async function testPorts() {
    portTestLoading.value = true;
    portTestError.value = null;
    portTestResults.value = [];
    try {
        const ports = String(form.http_ports_to_test || '')
            .split(/[,\s]+/)
            .map((p) => parseInt(p, 10))
            .filter((p) => !Number.isNaN(p) && p > 0);
        const { data } = await window.axios.post(route('sms.config.test-ports'), {
            ports: ports.length ? ports : [8080],
            api_key: form.config_callbacks.api_key || form.api_key,
            service_base_url: form.service_base_url,
        });
        portTestResults.value = data.results ?? [];
    } catch (e) {
        const status = e?.response?.status;
        const msg = e?.response?.data?.message
            || e?.response?.data?.errors
            || e.message
            || 'Port test failed.';
        portTestError.value = typeof msg === 'string'
            ? (status ? `HTTP ${status}: ${msg}` : msg)
            : JSON.stringify(msg);
    } finally {
        portTestLoading.value = false;
    }
}

function syncConfig() {
    router.post(route('sms.config.sync'), {}, { preserveScroll: true });
}

function openCreateGroup() {
    editingGroup.value = null;
    groupForm.reset();
    groupForm.clearErrors();
    groupForm.name = '';
    groupForm.enabled = true;
    groupForm.sort_order = 0;
    showGroupModal.value = true;
}

function openEditGroup(group) {
    editingGroup.value = group;
    groupForm.clearErrors();
    groupForm.name = group.name;
    groupForm.enabled = !!group.enabled;
    groupForm.sort_order = group.sort_order ?? 0;
    showGroupModal.value = true;
}

function submitGroup() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showGroupModal.value = false;
            groupForm.reset();
        },
    };
    if (editingGroup.value) {
        groupForm.put(route('sms.device-groups.update', editingGroup.value.id), options);
    } else {
        groupForm.post(route('sms.device-groups.store'), options);
    }
}

function deleteGroup(group) {
    if (group.is_default) {
        alert('The Default device group cannot be deleted.');
        return;
    }
    if (group.devices_count > 0) {
        alert('Move or delete devices in this group before removing it.');
        return;
    }
    if (!confirm(`Remove device group “${group.name}”?`)) return;
    router.delete(route('sms.device-groups.destroy', group.id), { preserveScroll: true });
}

function openCreateDevice(groupId = null) {
    editingDevice.value = null;
    deviceForm.reset();
    deviceForm.clearErrors();
    deviceForm.sms_device_group_id = groupId ?? props.deviceGroups?.[0]?.id ?? null;
    deviceForm.type = 'AT';
    deviceForm.enabled = true;
    deviceForm.sort_order = 0;
    deviceForm.runtime_device_id = '';
    deviceForm.config = {
        port: '',
        baud_rate: 115200,
        demo_send_success_rate: 0.99,
        demo_receive_interval_seconds: 300,
    };
    showDeviceModal.value = true;
}

function openEditDevice(device) {
    editingDevice.value = device;
    deviceForm.clearErrors();
    deviceForm.sms_device_group_id = device.sms_device_group_id;
    deviceForm.type = device.type;
    deviceForm.name = device.name;
    deviceForm.enabled = !!device.enabled;
    deviceForm.sort_order = device.sort_order ?? 0;
    deviceForm.runtime_device_id = device.runtime_device_id ?? '';
    const cfg = device.config ?? {};
    deviceForm.config = {
        port: cfg.port || cfg.com_port || '',
        baud_rate: cfg.baud_rate ?? cfg.baud ?? 115200,
        demo_send_success_rate: cfg.demo_send_success_rate ?? 0.99,
        demo_receive_interval_seconds: cfg.demo_receive_interval_seconds ?? 300,
    };
    showDeviceModal.value = true;
}

function submitDevice() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showDeviceModal.value = false;
            deviceForm.reset();
        },
    };
    if (editingDevice.value) {
        deviceForm.put(route('sms.devices.update', editingDevice.value.id), options);
    } else {
        deviceForm.post(route('sms.devices.store'), options);
    }
}

function deleteDevice(device) {
    if (!confirm(`Remove device “${device.name}”?`)) return;
    router.delete(route('sms.devices.destroy', device.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="SMS Configuration" />
    <AppLayout>
        <template #header>SMS Configuration</template>

        <div class="max-w-4xl space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="page-title">SMS Configuration</h1>
                    <p class="mt-1 text-sm" style="color: var(--color-text-muted)">
                        Edit config.json sections and device registry. The SMS service is started manually
                        from the SMS Dashboard — this page never auto-starts it.
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span
                            class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium"
                            :style="service.running
                                ? { borderColor: '#15803d', color: '#15803d', background: '#f0fdf4' }
                                : { borderColor: 'var(--color-danger, #b91c1c)', color: 'var(--color-danger, #b91c1c)', background: 'var(--color-bg-surface)' }"
                        >
                            {{ service.running ? 'Running' : 'Down' }}
                        </span>
                        <span v-if="!service.running && service.error" class="text-xs" style="color: var(--color-text-muted)">
                            {{ service.error }}
                        </span>
                    </div>
                </div>
                <Link :href="route('sms.dashboard')" class="text-sm underline">
                    SMS Dashboard
                </Link>
            </div>

            <form class="space-y-4" @submit.prevent="saveSettings">
                <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <h2 class="text-sm font-semibold">Laravel connection</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="form-label block mb-1">Service base URL (Laravel → C++ API)</label>
                            <Input v-model="form.service_base_url" class="w-full" :placeholder="D.service_base_url" />
                            <InputError :message="form.errors.service_base_url" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label block mb-1">Config.json path</label>
                            <Input
                                v-model="form.config_json_path"
                                class="w-full"
                                :placeholder="D.config_json_path"
                            />
                            <p class="text-xs mt-1" style="color: var(--color-text-muted)">
                                Required. Laravel writes the full config document here on save/sync.
                            </p>
                            <InputError :message="form.errors.config_json_path" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label block mb-1">Service executable path (optional)</label>
                            <Input v-model="form.service_exe_path" class="w-full" :placeholder="D.service_exe_path" />
                            <p class="text-xs mt-1" style="color: var(--color-text-muted)">
                                Used only by Dashboard Start / Stop / Restart — not required to write config.json.
                            </p>
                            <InputError :message="form.errors.service_exe_path" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">Health and list device check interval (seconds)</label>
                            <Input
                                v-model="form.dashboard_health_poll_seconds"
                                type="number"
                                min="30"
                                max="3600"
                                class="w-full"
                                :placeholder="String(D.dashboard_health_poll_seconds)"
                            />
                            <p class="text-xs mt-1" style="color: var(--color-text-muted)">
                                How often the SMS Dashboard polls Laravel → C++ /ping, /health, and /devices (30–3600). Default 300 (5 minutes). Not the per-device health callback interval in config.json.
                            </p>
                            <InputError :message="form.errors.dashboard_health_poll_seconds" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">Pending batches and received SMS refresh (seconds)</label>
                            <Input
                                v-model="form.dashboard_list_poll_seconds"
                                type="number"
                                min="5"
                                max="300"
                                class="w-full"
                                :placeholder="String(D.dashboard_list_poll_seconds)"
                            />
                            <p class="text-xs mt-1" style="color: var(--color-text-muted)">
                                How often the SMS Dashboard refreshes pending batches and unmatched received SMS (5–300). Default 8.
                            </p>
                            <InputError :message="form.errors.dashboard_list_poll_seconds" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label block mb-1">HTTP ports to test</label>
                            <div class="flex flex-wrap gap-2">
                                <Input v-model="form.http_ports_to_test" class="flex-1 min-w-[12rem]" :placeholder="String(D.config_service.listen_port)" />
                                <Button type="button" variant="outline" :disabled="portTestLoading" @click="testPorts">
                                    {{ portTestLoading ? 'Testing…' : 'Test ports' }}
                                </Button>
                            </div>
                            <p class="text-xs mt-1" style="color: var(--color-text-muted)">
                                Probe only — never starts the SMS service.
                            </p>
                            <p v-if="portTestError" class="text-sm mt-1" style="color: var(--color-danger, #b91c1c)">
                                {{ portTestError }}
                            </p>
                            <ul v-if="portTestResults.length" class="mt-2 text-sm space-y-1">
                                <li v-for="r in portTestResults" :key="r.port">
                                    <template v-if="r.ping || r.health">
                                        Port {{ r.port }}:
                                        <span v-if="r.ping">ping OK</span>
                                        <span v-if="r.health"> · health {{ r.health }}</span>
                                    </template>
                                    <template v-else>
                                        Port {{ r.port }}:
                                        <span style="color: var(--color-danger, #b91c1c)">{{ r.error || 'unreachable' }}</span>
                                    </template>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <h2 class="text-sm font-semibold">service</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label block mb-1">service_id</label>
                            <Input v-model="form.config_service.service_id" class="w-full" :placeholder="D.config_service.service_id" />
                            <InputError :message="form.errors['config_service.service_id']" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">friendly_name</label>
                            <Input v-model="form.config_service.friendly_name" class="w-full" :placeholder="D.config_service.friendly_name" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">listen_address</label>
                            <Input v-model="form.config_service.listen_address" class="w-full" :placeholder="D.config_service.listen_address" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">listen_port</label>
                            <Input v-model="form.config_service.listen_port" type="number" class="w-full" :placeholder="String(D.config_service.listen_port)" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">timezone</label>
                            <Input v-model="form.config_service.timezone" class="w-full" :placeholder="D.config_service.timezone" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">shutdown_timeout_seconds</label>
                            <Input v-model="form.config_service.shutdown_timeout_seconds" type="number" class="w-full" :placeholder="String(D.config_service.shutdown_timeout_seconds)" />
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-2">
                            <Checkbox v-model:checked="form.config_service.auto_detect_at_devices" />
                            <span class="text-sm">auto_detect_at_devices</span>
                        </div>
                        <p
                            v-if="form.config_service.auto_detect_at_devices"
                            class="sm:col-span-2 text-xs"
                            style="color: var(--color-text-muted)"
                        >
                            When true, C++ discovers AT modems and ignores configured AT registry rows.
                            Demo / Huawei / GOIP still load from devices[].
                        </p>
                    </div>
                </section>

                <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <h2 class="text-sm font-semibold">logging</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label block mb-1">level</label>
                            <Input v-model="form.config_logging.level" class="w-full" :placeholder="D.config_logging.level" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">directory</label>
                            <Input v-model="form.config_logging.directory" class="w-full" :placeholder="D.config_logging.directory" />
                            <InputError :message="form.errors['config_logging.directory']" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">max_file_size_mb</label>
                            <Input v-model="form.config_logging.max_file_size_mb" type="number" class="w-full" :placeholder="String(D.config_logging.max_file_size_mb)" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">max_files</label>
                            <Input v-model="form.config_logging.max_files" type="number" class="w-full" :placeholder="String(D.config_logging.max_files)" />
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-2">
                            <Checkbox v-model:checked="form.config_logging.console" />
                            <span class="text-sm">console</span>
                        </div>
                    </div>
                </section>

                <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <h2 class="text-sm font-semibold">http</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label block mb-1">request_timeout_seconds</label>
                            <Input v-model="form.config_http.request_timeout_seconds" type="number" class="w-full" :placeholder="String(D.config_http.request_timeout_seconds)" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">worker_threads</label>
                            <Input v-model="form.config_http.worker_threads" type="number" class="w-full" :placeholder="String(D.config_http.worker_threads)" />
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-2">
                            <Checkbox v-model:checked="form.config_http.keep_alive" />
                            <span class="text-sm">keep_alive</span>
                        </div>
                    </div>
                </section>

                <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <h2 class="text-sm font-semibold">callbacks</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="form-label block mb-1">base_url</label>
                            <Input v-model="form.config_callbacks.base_url" class="w-full" :placeholder="D.config_callbacks.base_url" />
                            <InputError :message="form.errors['config_callbacks.base_url']" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label block mb-1">api_key</label>
                            <Input v-model="form.config_callbacks.api_key" class="w-full" type="password" autocomplete="off" :placeholder="D.config_callbacks.api_key" />
                            <InputError :message="form.errors['config_callbacks.api_key']" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">retry_attempts</label>
                            <Input v-model="form.config_callbacks.retry_attempts" type="number" class="w-full" :placeholder="String(D.config_callbacks.retry_attempts)" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">retry_delay_seconds</label>
                            <Input v-model="form.config_callbacks.retry_delay_seconds" type="number" class="w-full" :placeholder="String(D.config_callbacks.retry_delay_seconds)" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">connect_timeout_seconds</label>
                            <Input v-model="form.config_callbacks.connect_timeout_seconds" type="number" class="w-full" :placeholder="String(D.config_callbacks.connect_timeout_seconds)" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">request_timeout_seconds</label>
                            <Input v-model="form.config_callbacks.request_timeout_seconds" type="number" class="w-full" :placeholder="String(D.config_callbacks.request_timeout_seconds)" />
                        </div>
                    </div>
                </section>

                <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <h2 class="text-sm font-semibold">queue</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label block mb-1">max_size</label>
                            <Input v-model="form.config_queue.max_size" type="number" class="w-full" :placeholder="String(D.config_queue.max_size)" />
                        </div>
                        <div>
                            <label class="form-label block mb-1">worker_threads</label>
                            <Input v-model="form.config_queue.worker_threads" type="number" class="w-full" :placeholder="String(D.config_queue.worker_threads)" />
                        </div>
                    </div>
                </section>

                <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model:checked="form.sync_config_json" />
                        <span class="text-sm">Write full config.json on save</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" :disabled="form.processing">Save settings</Button>
                        <Button type="button" variant="outline" @click="syncConfig">Sync config.json now</Button>
                    </div>
                </section>
            </form>

            <section class="space-y-3 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Device groups</h2>
                    <Button type="button" size="sm" @click="openCreateGroup">Add group</Button>
                </div>
                <p class="text-xs" style="color: var(--color-text-muted)">
                    Create a group first, then add SMS devices under it.
                </p>
                <div v-if="!deviceGroups.length" class="text-sm" style="color: var(--color-text-muted)">
                    No device groups yet.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left" style="color: var(--color-text-muted)">
                                <th class="py-2 pr-3">Name</th>
                                <th class="py-2 pr-3">Enabled</th>
                                <th class="py-2 pr-3">Devices</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in deviceGroups" :key="group.id" class="border-t" style="border-color: var(--color-border)">
                                <td class="py-2 pr-3">{{ group.name }}</td>
                                <td class="py-2 pr-3">{{ group.enabled ? 'Yes' : 'No' }}</td>
                                <td class="py-2 pr-3">{{ group.devices_count }}</td>
                                <td class="py-2 space-x-2">
                                    <button type="button" class="underline" @click="openEditGroup(group)">Edit</button>
                                    <button
                                        type="button"
                                        class="underline"
                                        :disabled="group.is_default || group.devices_count > 0"
                                        @click="deleteGroup(group)"
                                    >Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-4 rounded border p-4" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Devices by group</h2>
                    <Button type="button" size="sm" :disabled="!deviceGroups.length" @click="openCreateDevice()">Add device</Button>
                </div>
                <div v-if="!deviceGroups.length" class="text-sm" style="color: var(--color-text-muted)">
                    Create a device group before adding devices.
                </div>
                <div v-for="group in devicesByGroup" :key="group.id" class="space-y-2 rounded border p-3" style="border-color: var(--color-border)">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-medium">
                            {{ group.name }}
                            <span class="font-normal" style="color: var(--color-text-muted)">({{ group.devices.length }})</span>
                        </div>
                        <Button type="button" size="sm" variant="outline" @click="openCreateDevice(group.id)">Add device</Button>
                    </div>
                    <div v-if="!group.devices.length" class="text-xs" style="color: var(--color-text-muted)">No devices in this group.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left" style="color: var(--color-text-muted)">
                                    <th class="py-1 pr-3">Name</th>
                                    <th class="py-1 pr-3">Type</th>
                                    <th class="py-1 pr-3">Runtime ID</th>
                                    <th class="py-1 pr-3">Port</th>
                                    <th class="py-1 pr-3">Enabled</th>
                                    <th class="py-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="device in group.devices" :key="device.id" class="border-t" style="border-color: var(--color-border)">
                                    <td class="py-1 pr-3">{{ device.name }}</td>
                                    <td class="py-1 pr-3">{{ device.type }}</td>
                                    <td class="py-1 pr-3">{{ device.runtime_device_id || '—' }}</td>
                                    <td class="py-1 pr-3">{{ device.config?.port || device.config?.com_port || '—' }}</td>
                                    <td class="py-1 pr-3">{{ device.enabled ? 'Yes' : 'No' }}</td>
                                    <td class="py-1 space-x-2">
                                        <button type="button" class="underline" @click="openEditDevice(device)">Edit</button>
                                        <button type="button" class="underline" @click="deleteDevice(device)">Delete</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="showGroupModal" max-width="md" @close="showGroupModal = false">
            <form class="p-6 space-y-3" @submit.prevent="submitGroup">
                <h2 class="text-lg font-semibold">
                    {{ editingGroup ? 'Edit device group' : 'Add device group' }}
                </h2>
                <div>
                    <label class="form-label block mb-1">Name</label>
                    <Input v-model="groupForm.name" class="w-full" :disabled="editingGroup?.is_default" />
                    <InputError :message="groupForm.errors.name" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model:checked="groupForm.enabled" />
                    <span class="text-sm">Enabled</span>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" @click="showGroupModal = false">Cancel</Button>
                    <Button type="submit" :disabled="groupForm.processing">Save group</Button>
                </div>
            </form>
        </Modal>

        <Modal :show="showDeviceModal" max-width="lg" @close="showDeviceModal = false">
            <form class="p-6 space-y-3" @submit.prevent="submitDevice">
                <h2 class="text-lg font-semibold">
                    {{ editingDevice ? 'Edit SMS device' : 'Add SMS device' }}
                </h2>
                <div>
                    <label class="form-label block mb-1">Device group</label>
                    <Select
                        v-model="deviceForm.sms_device_group_id"
                        :options="deviceGroupOptions"
                        option-label="name"
                        option-value="id"
                        class="w-full"
                    />
                    <InputError :message="deviceForm.errors.sms_device_group_id" />
                </div>
                <div>
                    <label class="form-label block mb-1">Type</label>
                    <Select
                        v-model="deviceForm.type"
                        :options="deviceTypeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                    <InputError :message="deviceForm.errors.type" />
                </div>
                <div>
                    <label class="form-label block mb-1">Name</label>
                    <Input v-model="deviceForm.name" class="w-full" />
                    <InputError :message="deviceForm.errors.name" />
                </div>
                <div>
                    <label class="form-label block mb-1">Runtime device ID (optional)</label>
                    <Input v-model="deviceForm.runtime_device_id" class="w-full" placeholder="SIM-DEMO" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label block mb-1">Port</label>
                        <Input
                            v-model="deviceForm.config.port"
                            class="w-full"
                            :placeholder="isDemoDevice ? 'COM99' : 'COM3'"
                        />
                        <InputError :message="deviceForm.errors['config.port']" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Baud rate</label>
                        <Input v-model="deviceForm.config.baud_rate" type="number" class="w-full" />
                    </div>
                </div>
                <div v-if="isDemoDevice" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label block mb-1">Demo send success rate</label>
                        <Input
                            v-model="deviceForm.config.demo_send_success_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="1"
                            class="w-full"
                        />
                        <p class="mt-1 text-xs" style="color: var(--color-text-muted)">0.0–1.0 (default 0.99)</p>
                        <InputError :message="deviceForm.errors['config.demo_send_success_rate']" />
                    </div>
                    <div>
                        <label class="form-label block mb-1">Demo receive interval (sec)</label>
                        <Input
                            v-model="deviceForm.config.demo_receive_interval_seconds"
                            type="number"
                            min="1"
                            class="w-full"
                        />
                        <p class="mt-1 text-xs" style="color: var(--color-text-muted)">Inbound every N seconds (default 300)</p>
                        <InputError :message="deviceForm.errors['config.demo_receive_interval_seconds']" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model:checked="deviceForm.enabled" />
                    <span class="text-sm">Enabled</span>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" @click="showDeviceModal = false">Cancel</Button>
                    <Button type="submit" :disabled="deviceForm.processing">Save device</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
