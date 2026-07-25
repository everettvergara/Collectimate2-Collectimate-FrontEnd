<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: [String, Number], default: undefined },
    class: { type: [String, Object, Array], default: undefined },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: undefined },
    id: { type: String, default: undefined },
    name: { type: String, default: undefined },
    rows: { type: [String, Number], default: 3 },
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>

<template>
    <textarea
        v-model="value"
        :disabled="disabled"
        :placeholder="placeholder"
        :id="id"
        :name="name"
        :rows="rows"
        :class="
            cn(
                'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    />
</template>
