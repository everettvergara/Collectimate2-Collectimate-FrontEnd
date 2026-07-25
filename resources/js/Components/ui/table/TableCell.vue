<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: { type: [String, Object, Array], default: undefined },
});
</script>

<template>
    <td :class="cn('p-3 align-middle [&:has([role=checkbox])]:pr-0', props.class)">
        <slot />
    </td>
</template>
