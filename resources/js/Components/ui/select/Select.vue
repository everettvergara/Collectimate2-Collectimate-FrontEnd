<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: null },
    options: { type: Array, default: () => [] },
    optionLabel: { type: String, default: 'label' },
    optionValue: { type: String, default: 'value' },
    placeholder: { type: String, default: 'Select…' },
    class: { type: [String, Object, Array], default: undefined },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: undefined },
    name: { type: String, default: undefined },
    showClear: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const normalizedOptions = computed(() =>
    (props.options ?? []).map((opt) => {
        if (opt !== null && typeof opt === 'object') {
            return {
                label: opt[props.optionLabel] ?? opt.label ?? String(opt[props.optionValue] ?? opt.value ?? ''),
                value: opt[props.optionValue] ?? opt.value,
            };
        }
        return { label: String(opt), value: opt };
    }),
);

const value = computed({
    get: () => (props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue)),
    set: (raw) => {
        if (raw === '') {
            emit('update:modelValue', null);
            return;
        }
        const match = normalizedOptions.value.find((o) => String(o.value) === raw);
        emit('update:modelValue', match ? match.value : raw);
    },
});
</script>

<template>
    <select
        v-model="value"
        :id="id"
        :name="name"
        :disabled="disabled"
        :class="
            cn(
                'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    >
        <option value="" :disabled="!showClear">{{ placeholder }}</option>
        <option v-for="opt in normalizedOptions" :key="String(opt.value)" :value="String(opt.value)">
            {{ opt.label }}
        </option>
    </select>
</template>
