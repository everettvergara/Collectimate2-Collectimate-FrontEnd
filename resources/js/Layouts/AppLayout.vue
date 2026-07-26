<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Menu, PanelLeftClose, PanelLeftOpen, X } from '@lucide/vue';
import { toast } from 'vue-sonner';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Toaster } from '@/Components/ui/sonner';

const SIDEBAR_STORAGE_KEY = 'collectimate.sidebarOpen';

const page = usePage();
const mobileOpen = ref(false);
const sidebarOpen = ref(true);

onMounted(() => {
    try {
        const stored = localStorage.getItem(SIDEBAR_STORAGE_KEY);
        if (stored === '0') {
            sidebarOpen.value = false;
        } else if (stored === '1') {
            sidebarOpen.value = true;
        }
    } catch {
        // ignore storage errors
    }
});

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
    try {
        localStorage.setItem(SIDEBAR_STORAGE_KEY, sidebarOpen.value ? '1' : '0');
    } catch {
        // ignore storage errors
    }
}

function closeMobileNav() {
    mobileOpen.value = false;
}

const asideClass = computed(() => {
    if (mobileOpen.value) {
        return 'fixed inset-y-0 left-0 z-40 flex';
    }
    if (sidebarOpen.value) {
        return 'hidden md:flex';
    }
    return 'hidden';
});

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
        items: [
            { label: 'Dashboard', href: 'dashboard', match: 'dashboard' },
            { label: 'SMS Dashboard', href: 'sms.dashboard', match: 'sms.dashboard' },
        ],
    },
    {
        label: 'CRM',
        items: [
            { label: 'Entities', href: 'entities.index', match: 'entities.*' },
            { label: 'Account Master', href: 'accounts.index', match: 'accounts.*' },
            { label: 'Activity Types', href: 'activity-types.index', match: 'activity-types.*' },
            { label: 'Contact Types', href: 'contact-types.index', match: 'contact-types.*' },
            { label: 'Address Types', href: 'address-types.index', match: 'address-types.*' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Reports', href: 'reports.index', match: 'reports.*' },
            { label: 'Import', href: 'imports.index', match: 'imports.*' },
            { label: 'SMS Batches', href: 'sms.batches.index', match: 'sms.batches*' },
            { label: 'SMS Received', href: 'sms.received.index', match: 'sms.received*' },
            { label: 'SMS Callbacks', href: 'sms.callbacks.index', match: 'sms.callbacks*' },
            { label: 'SMS Configuration', href: 'sms.config', match: 'sms.config*' },
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
            { label: 'Demo Mode', href: 'demo-mode.index', match: 'demo-mode.*' },
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
            toast.success(message);
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (message) {
            toast.error(message);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div>
        <div class="min-h-screen flex" style="background: var(--color-bg-app)">
            <aside
                class="w-60 shrink-0 flex-col"
                style="background: var(--color-nav-bg); color: var(--color-nav-text)"
                :class="asideClass"
            >
                <div class="px-4 py-4 border-b" style="border-color: var(--color-nav-hover)">
                    <Link
                        :href="safeHref('dashboard')"
                        class="page-title"
                        style="color: var(--color-nav-text-active)"
                        @click="closeMobileNav"
                    >
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
                                    @click="closeMobileNav"
                                >
                                    {{ item.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </nav>
            </aside>

            <div
                v-if="mobileOpen"
                class="fixed inset-0 z-30 bg-black/40 md:hidden"
                aria-hidden="true"
                @click="closeMobileNav"
            />

            <div class="flex-1 min-w-0 flex flex-col">
                <header
                    class="h-12 flex items-center justify-between gap-3 px-4 border-b"
                    style="background: var(--color-bg-surface); border-color: var(--color-border)"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            type="button"
                            class="md:hidden inline-flex h-9 w-9 items-center justify-center rounded shrink-0"
                            style="color: var(--color-text-muted)"
                            :aria-label="mobileOpen ? 'Close navigation' : 'Open navigation'"
                            :title="mobileOpen ? 'Close navigation' : 'Open navigation'"
                            @click="mobileOpen = !mobileOpen"
                        >
                            <X v-if="mobileOpen" class="h-4 w-4" />
                            <Menu v-else class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="hidden md:inline-flex h-9 w-9 items-center justify-center rounded shrink-0"
                            style="color: var(--color-text-muted)"
                            :aria-label="sidebarOpen ? 'Hide navigation' : 'Show navigation'"
                            :title="sidebarOpen ? 'Hide navigation' : 'Show navigation'"
                            @click="toggleSidebar"
                        >
                            <PanelLeftClose v-if="sidebarOpen" class="h-4 w-4" />
                            <PanelLeftOpen v-else class="h-4 w-4" />
                        </button>
                        <div class="page-title truncate">
                            <slot name="header" />
                        </div>
                    </div>
                    <div class="shrink-0" style="color: var(--color-text-muted); font-size: var(--font-size-base)">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded px-1.5 py-1 transition-colors hover:bg-[var(--color-bg-subtle)] focus:outline-none"
                                    style="color: var(--color-text-muted); font-size: var(--font-size-base)"
                                >
                                    <img
                                        v-if="page.props.auth?.user?.avatar_url"
                                        :src="page.props.auth.user.avatar_url"
                                        alt=""
                                        class="h-7 w-7 rounded-full object-cover shrink-0"
                                    />
                                    <span
                                        v-else
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full shrink-0"
                                        style="background: var(--color-bg-subtle); color: var(--color-text-label)"
                                    >
                                        {{ userLabel?.charAt(0)?.toUpperCase() || '?' }}
                                    </span>
                                    <span class="truncate max-w-[10rem]">{{ userLabel }}</span>
                                    <ChevronDown class="h-3.5 w-3.5 shrink-0" />
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="safeHref('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink :href="safeHref('profile.password.edit')">
                                    Change password
                                </DropdownLink>
                                <DropdownLink :href="safeHref('logout')" method="post" as="button">
                                    Logout
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </header>

                <main class="p-4 md:p-6">
                    <slot />
                </main>
            </div>
        </div>

        <Toaster />
    </div>
</template>
