<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: 'a',
    },
});
</script>

<template>
    <Link
        :href="href"
        :method="method"
        :as="as"
        class="block w-full px-4 py-2 text-start leading-5 transition duration-150 ease-in-out focus:outline-none hover:bg-[var(--color-bg-subtle)] focus:bg-[var(--color-bg-subtle)]"
        style="font-size: var(--font-size-base); color: var(--color-text); font-weight: var(--font-weight-regular)"
    >
        <slot />
    </Link>
</template>
