<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';

const page = usePage();
const toast = useToast();
const mobileOpen = ref(false);

const userLabel = computed(() => {
    const u = page.props.auth?.user;
    if (!u) return '';
    if (u.first_name || u.last_name) {
        return [u.first_name, u.last_name].filter(Boolean).join(' ');
    }
    return u.name || u.username || u.email;
});

const groups = [
    {
        label: 'Overview',
        items: [{ label: 'Dashboard', href: 'dashboard', match: 'dashboard' }],
    },
    {
        label: 'CRM',
        items: [
            { label: 'Entities', href: 'entities.index', match: 'entities.*' },
            { label: 'Campaigns', href: 'campaigns.index', match: 'campaigns.*' },
            { label: 'Account Master', href: 'accounts.index', match: 'accounts.*' },
            { label: 'Status Management', href: 'statuses.index', match: 'statuses.*' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Reports', href: 'reports.index', match: 'reports.*' },
            { label: 'Import', href: 'imports.index', match: 'imports.*' },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', href: 'users.index', match: 'users.*' },
            { label: 'Roles & Permissions', href: 'roles.index', match: 'roles.*' },
            { label: 'Agent Profiles', href: 'agent-profiles.index', match: 'agent-profiles.*' },
            { label: 'Audit Logs', href: 'audit-logs.index', match: 'audit-logs.*' },
            { label: 'Settings', href: 'settings.index', match: 'settings.*' },
        ],
    },
    {
        label: 'Future',
        items: [
            { label: 'Knowledge Center', disabled: true },
            { label: 'SMS', disabled: true },
            { label: 'Calling', disabled: true },
            { label: 'Email', disabled: true },
            { label: 'Messaging', disabled: true },
            { label: 'AI', disabled: true },
            { label: 'Analytics', disabled: true },
        ],
    },
];

function isActive(match) {
    if (!match) return false;
    try {
        return route().current(match);
    } catch {
        return false;
    }
}

function safeHref(name) {
    try {
        return route(name);
    } catch {
        return '#';
    }
}

watch(
    () => page.props.flash?.success,
    (message) => {
        if (message) {
            toast.add({ severity: 'success', summary: message, life: 4000 });
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (message) {
            toast.add({ severity: 'error', summary: message, life: 5000 });
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="min-h-screen flex" style="background: var(--color-bg-app)">
        <Toast />
        <ConfirmDialog />

        <aside
            class="w-60 shrink-0 flex flex-col"
            style="background: var(--color-nav-bg); color: var(--color-nav-text)"
            :class="mobileOpen ? 'fixed inset-y-0 left-0 z-40' : 'hidden md:flex'"
        >
            <div class="px-4 py-4 border-b" style="border-color: var(--color-nav-hover)">
                <Link :href="safeHref('dashboard')" class="page-title" style="color: var(--color-nav-text-active)">
                    Collectimate
                </Link>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-4">
                <div v-for="group in groups" :key="group.label">
                    <div class="nav-group-label px-2 mb-1">{{ group.label }}</div>
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.label">
                            <span
                                v-if="item.disabled"
                                class="block px-2 py-1.5 rounded opacity-40 cursor-not-allowed"
                            >
                                {{ item.label }}
                            </span>
                            <Link
                                v-else
                                :href="safeHref(item.href)"
                                class="block px-2 py-1.5 rounded"
                                :style="{
                                    background: isActive(item.match) ? 'var(--color-nav-hover)' : 'transparent',
                                    color: isActive(item.match)
                                        ? 'var(--color-nav-text-active)'
                                        : 'var(--color-nav-text)',
                                }"
                            >
                                {{ item.label }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <div class="flex-1 min-w-0 flex flex-col">
            <header
                class="h-12 flex items-center justify-between px-4 border-b"
                style="background: var(--color-bg-surface); border-color: var(--color-border)"
            >
                <button type="button" class="md:hidden text-sm" @click="mobileOpen = !mobileOpen">
                    Menu
                </button>
                <div class="page-title">
                    <slot name="header" />
                </div>
                <div class="flex items-center gap-3 text-sm" style="color: var(--color-text-muted)">
                    <span>{{ userLabel }}</span>
                    <Link :href="safeHref('profile.edit')" class="hover:underline">Profile</Link>
                    <Link :href="safeHref('logout')" method="post" as="button" class="hover:underline">
                        Logout
                    </Link>
                </div>
            </header>

            <main class="p-4 md:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
