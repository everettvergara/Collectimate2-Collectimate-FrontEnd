<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: { type: [String, Object, Array], default: undefined },
});
</script>

<template>
    <th
        :class="
            cn(
                'h-10 px-3 text-left align-middle text-muted-foreground [&:has([role=checkbox])]:pr-0',
                props.class,
            )
        "
    >
        <slot />
    </th>
</template>
