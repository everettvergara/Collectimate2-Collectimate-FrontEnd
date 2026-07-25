<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: { type: [String, Object, Array], default: undefined },
});
</script>

<template>
    <div class="relative w-full overflow-auto">
        <table :class="cn('w-full caption-bottom text-sm', props.class)">
            <slot />
        </table>
    </div>
</template>
