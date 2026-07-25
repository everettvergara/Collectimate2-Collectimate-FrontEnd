<script setup>
import { Primitive } from 'reka-ui';
import { cn } from '@/lib/utils';
import { buttonVariants } from '.';

const props = defineProps({
    variant: { type: String, default: 'default' },
    size: { type: String, default: 'default' },
    as: { type: String, default: 'button' },
    asChild: { type: Boolean, default: false },
    class: { type: [String, Object, Array], default: undefined },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :type="as === 'button' ? type : undefined"
        :disabled="disabled"
        :class="cn(buttonVariants({ variant, size }), props.class)"
    >
        <slot />
    </Primitive>
</template>
