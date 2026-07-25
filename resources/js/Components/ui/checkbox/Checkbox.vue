<script setup>
import { computed } from 'vue';
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';
import { Check } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: [Boolean, String, Number], default: false },
    class: { type: [String, Object, Array], default: undefined },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue']);

const checked = computed({
    get: () => !!props.modelValue,
    set: (v) => emit('update:modelValue', !!v),
});
</script>

<template>
    <CheckboxRoot
        v-model="checked"
        :id="id"
        :disabled="disabled"
        :class="
            cn(
                'peer h-4 w-4 shrink-0 rounded-sm border border-primary shadow focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
                props.class,
            )
        "
    >
        <CheckboxIndicator class="flex items-center justify-center text-current">
            <Check class="h-3.5 w-3.5" />
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
