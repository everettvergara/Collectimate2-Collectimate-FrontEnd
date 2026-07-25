<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: { type: [String, Object, Array], default: undefined },
});
</script>

<template>
    <tr :class="cn('border-b transition-colors', props.class)">
        <slot />
    </tr>
</template>
