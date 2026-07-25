<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Select } from '@/Components/ui/select';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            entity_id: null,
        }),
    },
    filterOptions: {
        type: Object,
        default: () => ({
            entities: [],
        }),
    },
    activityToday: {
        type: Array,
        default: () => [],
    },
    portfolio: {
        type: Object,
        default: () => ({
            entity: null,
            statuses: [],
            rows: [],
            totals: { status_counts: {}, total: 0 },
        }),
    },
    agents: {
        type: Array,
        default: () => [],
    },
});

const entityId = ref(props.filters?.entity_id ?? null);

watch(
    () => props.filters,
    (filters) => {
        entityId.value = filters?.entity_id ?? null;
    },
    { deep: true },
);

const entityOptions = computed(() => props.filterOptions?.entities ?? []);
const portfolioEntity = computed(() => props.portfolio?.entity ?? null);
const portfolioStatuses = computed(() => props.portfolio?.statuses ?? []);
const portfolioRows = computed(() => props.portfolio?.rows ?? []);
const portfolioTotals = computed(() => props.portfolio?.totals ?? { status_counts: {}, total: 0 });

function applyFilters(overrides = {}) {
    router.get(
        route('dashboard'),
        {
            entity_id: entityId.value || undefined,
            ...overrides,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onEntityChange(value) {
    entityId.value = value;
    applyFilters({
        entity_id: value || undefined,
    });
}

function statusKey(status) {
    return String(status.id);
}

function statusCount(row, status) {
    return row?.status_counts?.[statusKey(status)] ?? 0;
}

function totalStatusCount(status, totals) {
    return totals?.status_counts?.[statusKey(status)] ?? 0;
}

function portfolioTotalStatusCount(status) {
    return totalStatusCount(status, portfolioTotals.value);
}

function showPortfolioEntity(row, index) {
    if (index === 0) return true;
    const prev = portfolioRows.value[index - 1];
    return (prev?.entity_name ?? '') !== (row?.entity_name ?? '');
}

function statusHeaderStyle(status) {
    if (!status?.color) {
        return {};
    }

    return {
        background: status.color,
        color: status.text_color || '#ffffff',
    };
}

function formatCount(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return '0';
    return n.toLocaleString('en-US');
}

function accountsHref(params = {}) {
    const query = {};
    if (params.entity_id) query.entity_id = params.entity_id;
    if (params.campaign_id) query.campaign_id = params.campaign_id;
    if (params.entity_status_id && params.entity_status_id !== 'unassigned') {
        query.entity_status_id = params.entity_status_id;
    }
    return route('accounts.index', query);
}

function entityAccountsHref() {
    if (!portfolioEntity.value?.id) return null;
    return accountsHref({ entity_id: portfolioEntity.value.id });
}

function campaignAccountsHref(row) {
    if (!portfolioEntity.value?.id || !row?.campaign_id) return null;
    return accountsHref({
        entity_id: portfolioEntity.value.id,
        campaign_id: row.campaign_id,
    });
}

function statusAccountsHref(row, status) {
    if (!portfolioEntity.value?.id || !row?.campaign_id) return null;
    const key = statusKey(status);
    if (key === 'unassigned') {
        return accountsHref({
            entity_id: portfolioEntity.value.id,
            campaign_id: row.campaign_id,
        });
    }
    return accountsHref({
        entity_id: portfolioEntity.value.id,
        campaign_id: row.campaign_id,
        entity_status_id: key,
    });
}

function footerStatusAccountsHref(status) {
    if (!portfolioEntity.value?.id) return null;
    const key = statusKey(status);
    if (key === 'unassigned') {
        return accountsHref({ entity_id: portfolioEntity.value.id });
    }
    return accountsHref({
        entity_id: portfolioEntity.value.id,
        entity_status_id: key,
    });
}

function initials(name) {
    if (!name || name === '—') return '?';
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function formatAbsoluteTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatActivityTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    const divisions = [
        { amount: 60, unit: 'second' },
        { amount: 60, unit: 'minute' },
        { amount: 24, unit: 'hour' },
        { amount: 7, unit: 'day' },
        { amount: 4.34524, unit: 'week' },
        { amount: 12, unit: 'month' },
        { amount: Number.POSITIVE_INFINITY, unit: 'year' },
    ];

    let duration = diffSeconds;
    for (const division of divisions) {
        if (Math.abs(duration) < division.amount) {
            return rtf.format(Math.round(duration), division.unit);
        }
        duration /= division.amount;
    }

    return rtf.format(Math.round(duration), 'year');
}

function dash(value) {
    return value && String(value).trim() !== '' ? value : '—';
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>Dashboard</template>

        <div class="mb-6">
            <div class="mb-3">
                <div class="page-title text-lg">Activity today</div>
                <p class="text-sm mt-1" style="color: var(--color-text-muted)">
                    Today’s account status counts by campaign for each accessible entity (latest status per account).
                </p>
            </div>

            <p
                v-if="activityToday.length === 0"
                class="text-sm"
                style="color: var(--color-text-muted)"
            >
                No entities to show.
            </p>

            <div
                v-else
                class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2"
            >
                <div
                    v-for="block in activityToday"
                    :key="block.entity.id"
                    class="p-2 border rounded min-w-0"
                    style="background: var(--color-bg-surface); border-color: var(--color-border)"
                >
                    <div class="flex items-center gap-1.5 mb-1 min-w-0">
                        <div
                            class="h-5 w-5 rounded overflow-hidden shrink-0 border flex items-center justify-center text-[9px] font-medium"
                            style="background: var(--color-bg); border-color: var(--color-border); color: var(--color-text-muted)"
                        >
                            <img
                                v-if="block.entity.logo_url"
                                :src="block.entity.logo_url"
                                :alt="`${block.entity.name} logo`"
                                class="h-full w-full object-contain"
                            />
                            <span v-else>{{ (block.entity.name || '?').slice(0, 1).toUpperCase() }}</span>
                        </div>
                        <div class="text-xs font-medium truncate" :title="block.entity.name">
                            {{ block.entity.name }}
                        </div>
                    </div>

                    <p
                        v-if="block.rows.length === 0"
                        class="text-[11px] leading-4"
                        style="color: var(--color-text-muted)"
                    >
                        No activity today.
                    </p>

                    <div
                        v-else
                        class="overflow-x-auto"
                    >
                        <table class="w-full text-[11px] leading-4 border-collapse">
                            <thead>
                                <tr>
                                    <th
                                        class="text-left font-medium px-1 py-0.5 border-b whitespace-nowrap"
                                        style="border-color: var(--color-border); color: var(--color-text-muted)"
                                    >
                                        Campaign
                                    </th>
                                    <th
                                        v-for="status in block.statuses"
                                        :key="`today-${block.entity.id}-${statusKey(status)}`"
                                        class="text-center font-medium px-1 py-0.5 border-b whitespace-nowrap"
                                        style="border-color: var(--color-border)"
                                    >
                                        <span
                                            class="inline-block rounded px-1 py-0 text-[10px] leading-3"
                                            :style="statusHeaderStyle(status)"
                                        >
                                            {{ status.name }}
                                        </span>
                                    </th>
                                    <th
                                        class="text-right font-medium px-1 py-0.5 border-b whitespace-nowrap"
                                        style="border-color: var(--color-border); color: var(--color-text-muted)"
                                    >
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in block.rows"
                                    :key="`today-row-${block.entity.id}-${row.campaign_id}`"
                                >
                                    <td
                                        class="px-1 py-0.5 border-b whitespace-nowrap max-w-[7rem] truncate"
                                        style="border-color: var(--color-border)"
                                        :title="row.campaign_name"
                                    >
                                        {{ row.campaign_name }}
                                    </td>
                                    <td
                                        v-for="status in block.statuses"
                                        :key="`today-cell-${block.entity.id}-${row.campaign_id}-${statusKey(status)}`"
                                        class="px-1 py-0.5 border-b text-center tabular-nums"
                                        style="border-color: var(--color-border)"
                                    >
                                        {{ formatCount(statusCount(row, status)) }}
                                    </td>
                                    <td
                                        class="px-1 py-0.5 border-b text-right font-medium tabular-nums"
                                        style="border-color: var(--color-border)"
                                    >
                                        {{ formatCount(row.total) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="px-1 py-0.5 font-medium">
                                        Total
                                    </td>
                                    <td
                                        v-for="status in block.statuses"
                                        :key="`today-total-${block.entity.id}-${statusKey(status)}`"
                                        class="px-1 py-0.5 text-center font-medium tabular-nums"
                                    >
                                        {{ formatCount(totalStatusCount(status, block.totals)) }}
                                    </td>
                                    <td class="px-1 py-0.5 text-right font-medium tabular-nums">
                                        {{ formatCount(block.totals?.total ?? 0) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="mb-6 p-4 border rounded"
            style="background: var(--color-bg-surface); border-color: var(--color-border)"
        >
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between mb-4">
                <div>
                    <div class="page-title text-lg">Account Portfolio Summary</div>
                    <p class="text-sm mt-1" style="color: var(--color-text-muted)">
                        Status counts by campaign for the selected entity.
                    </p>
                </div>
                <div class="min-w-[12rem]">
                    <label class="form-label block mb-1">Entity</label>
                    <Select
                        :model-value="entityId"
                        :options="entityOptions"
                        option-label="name"
                        option-value="id"
                        placeholder="Select entity"
                        show-clear
                        class="w-full min-w-[12rem]"
                        @update:model-value="onEntityChange"
                    />
                </div>
            </div>

            <p
                v-if="!entityId"
                class="text-sm"
                style="color: var(--color-text-muted)"
            >
                Select an Entity to view portfolio.
            </p>

            <div
                v-else-if="portfolioRows.length === 0"
                class="text-sm"
                style="color: var(--color-text-muted)"
            >
                No campaigns found for this entity.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Entity
                            </th>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Campaign
                            </th>
                            <th
                                v-for="status in portfolioStatuses"
                                :key="statusKey(status)"
                                class="text-center font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border)"
                            >
                                <span
                                    class="inline-block rounded px-2 py-0.5 text-xs"
                                    :style="statusHeaderStyle(status)"
                                >
                                    {{ status.name }}
                                </span>
                            </th>
                            <th
                                class="text-right font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, rowIndex) in portfolioRows"
                            :key="row.campaign_id"
                        >
                            <td
                                class="px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border)"
                            >
                                <template v-if="showPortfolioEntity(row, rowIndex)">
                                    <Link
                                        v-if="entityAccountsHref()"
                                        :href="entityAccountsHref()"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="hover:underline"
                                        style="color: var(--color-text)"
                                    >
                                        {{ row.entity_name }}
                                    </Link>
                                    <span v-else>{{ row.entity_name }}</span>
                                </template>
                            </td>
                            <td
                                class="px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border)"
                            >
                                <Link
                                    v-if="campaignAccountsHref(row)"
                                    :href="campaignAccountsHref(row)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="hover:underline"
                                    style="color: var(--color-text)"
                                >
                                    {{ row.campaign_name }}
                                </Link>
                                <span v-else>{{ row.campaign_name }}</span>
                            </td>
                            <td
                                v-for="status in portfolioStatuses"
                                :key="`${row.campaign_id}-${statusKey(status)}`"
                                class="px-3 py-2 border-b text-center tabular-nums"
                                style="border-color: var(--color-border)"
                            >
                                <Link
                                    v-if="statusCount(row, status) > 0 && statusAccountsHref(row, status)"
                                    :href="statusAccountsHref(row, status)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="hover:underline"
                                    style="color: var(--color-text)"
                                >
                                    {{ formatCount(statusCount(row, status)) }}
                                </Link>
                                <span v-else>{{ formatCount(statusCount(row, status)) }}</span>
                            </td>
                            <td
                                class="px-3 py-2 border-b text-right font-medium tabular-nums"
                                style="border-color: var(--color-border)"
                            >
                                <Link
                                    v-if="row.total > 0 && campaignAccountsHref(row)"
                                    :href="campaignAccountsHref(row)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="hover:underline"
                                    style="color: var(--color-text)"
                                >
                                    {{ formatCount(row.total) }}
                                </Link>
                                <span v-else>{{ formatCount(row.total) }}</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td
                                class="px-3 py-2 font-medium"
                                colspan="2"
                            >
                                Total
                            </td>
                            <td
                                v-for="status in portfolioStatuses"
                                :key="`total-${statusKey(status)}`"
                                class="px-3 py-2 text-center font-medium tabular-nums"
                            >
                                <Link
                                    v-if="portfolioTotalStatusCount(status) > 0 && footerStatusAccountsHref(status)"
                                    :href="footerStatusAccountsHref(status)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="hover:underline"
                                    style="color: var(--color-text)"
                                >
                                    {{ formatCount(portfolioTotalStatusCount(status)) }}
                                </Link>
                                <span v-else>{{ formatCount(portfolioTotalStatusCount(status)) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-medium tabular-nums">
                                <Link
                                    v-if="(portfolioTotals.total ?? 0) > 0 && entityAccountsHref()"
                                    :href="entityAccountsHref()"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="hover:underline"
                                    style="color: var(--color-text)"
                                >
                                    {{ formatCount(portfolioTotals.total ?? 0) }}
                                </Link>
                                <span v-else>{{ formatCount(portfolioTotals.total ?? 0) }}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div
            class="p-4 border rounded"
            style="background: var(--color-bg-surface); border-color: var(--color-border)"
        >
            <div class="mb-4">
                <div class="page-title text-lg">Agents</div>
                <p class="text-sm mt-1" style="color: var(--color-text-muted)">
                    Active agents with today’s login status and last account activity.
                </p>
            </div>

            <p
                v-if="agents.length === 0"
                class="text-sm"
                style="color: var(--color-text-muted)"
            >
                No agents to show.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Agent
                            </th>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Status
                            </th>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Last activity
                            </th>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Last Entity / Campaign / Account
                            </th>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Last comment
                            </th>
                            <th
                                class="text-left font-medium px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border); color: var(--color-text-muted)"
                            >
                                Status / Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="agent in agents"
                            :key="agent.id"
                        >
                            <td
                                class="px-3 py-2 border-b"
                                style="border-color: var(--color-border)"
                            >
                                <div class="flex items-center gap-2 min-w-[10rem]">
                                    <div
                                        class="h-8 w-8 rounded-full overflow-hidden flex items-center justify-center text-xs font-medium shrink-0"
                                        style="background: var(--color-bg-subtle); color: var(--color-text-muted)"
                                    >
                                        <img
                                            v-if="agent.avatar_url"
                                            :src="agent.avatar_url"
                                            :alt="agent.name"
                                            class="h-full w-full object-cover"
                                        >
                                        <span v-else>{{ initials(agent.name) }}</span>
                                    </div>
                                    <span class="font-medium">{{ agent.name }}</span>
                                </div>
                            </td>
                            <td
                                class="px-3 py-2 border-b"
                                style="border-color: var(--color-border)"
                            >
                                <span
                                    class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                                    :style="agent.presence === 'online'
                                        ? { background: 'color-mix(in srgb, #16a34a 18%, transparent)', color: '#15803d' }
                                        : { background: 'color-mix(in srgb, #64748b 18%, transparent)', color: '#475569' }"
                                >
                                    {{ agent.presence === 'online' ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-2 border-b whitespace-nowrap"
                                style="border-color: var(--color-border)"
                                :title="formatAbsoluteTime(agent.last_activity_at)"
                            >
                                {{ formatActivityTime(agent.last_activity_at) }}
                            </td>
                            <td
                                class="px-3 py-2 border-b"
                                style="border-color: var(--color-border)"
                            >
                                <div class="min-w-[14rem]">
                                    <div>{{ dash(agent.last_entity) }}</div>
                                    <div style="color: var(--color-text-muted)">{{ dash(agent.last_campaign) }}</div>
                                    <div style="color: var(--color-text-muted)">{{ dash(agent.last_account) }}</div>
                                </div>
                            </td>
                            <td
                                class="px-3 py-2 border-b max-w-[16rem]"
                                style="border-color: var(--color-border)"
                            >
                                <span class="line-clamp-2">{{ dash(agent.last_comment) }}</span>
                            </td>
                            <td
                                class="px-3 py-2 border-b"
                                style="border-color: var(--color-border)"
                            >
                                <div class="min-w-[8rem]">
                                    <div>{{ dash(agent.last_status) }}</div>
                                    <div style="color: var(--color-text-muted)">{{ dash(agent.last_action) }}</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
