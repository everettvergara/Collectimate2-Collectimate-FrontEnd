<script setup>
import { computed } from 'vue';

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const activeId = computed(() => props.modelValue);

function selectTab(id) {
    if (id === activeId.value) return;
    emit('update:modelValue', id);
}

function onKeydown(event, index) {
    const tabs = props.tabs ?? [];
    if (!tabs.length) return;

    let next = index;
    if (event.key === 'ArrowRight') {
        next = (index + 1) % tabs.length;
    } else if (event.key === 'ArrowLeft') {
        next = (index - 1 + tabs.length) % tabs.length;
    } else if (event.key === 'Home') {
        next = 0;
    } else if (event.key === 'End') {
        next = tabs.length - 1;
    } else {
        return;
    }

    event.preventDefault();
    selectTab(tabs[next].id);
    const buttons = event.currentTarget.parentElement?.querySelectorAll('[role="tab"]');
    buttons?.[next]?.focus();
}
</script>

<template>
    <div class="space-y-4">
        <div
            role="tablist"
            class="flex flex-wrap gap-1 border-b"
            style="border-color: var(--color-border)"
        >
            <button
                v-for="(tab, index) in tabs"
                :id="`tab-${tab.id}`"
                :key="tab.id"
                type="button"
                role="tab"
                class="px-3 py-2 text-sm transition-colors"
                :aria-selected="activeId === tab.id"
                :aria-controls="`tab-panel-${tab.id}`"
                :tabindex="activeId === tab.id ? 0 : -1"
                :style="
                    activeId === tab.id
                        ? {
                              color: 'var(--color-primary)',
                              borderBottom: '2px solid var(--color-primary)',
                              marginBottom: '-1px',
                          }
                        : {
                              color: 'var(--color-text-muted)',
                              borderBottom: '2px solid transparent',
                              marginBottom: '-1px',
                          }
                "
                @click="selectTab(tab.id)"
                @keydown="onKeydown($event, index)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div
            v-for="tab in tabs"
            :id="`tab-panel-${tab.id}`"
            :key="`panel-${tab.id}`"
            role="tabpanel"
            :aria-labelledby="`tab-${tab.id}`"
            :hidden="activeId !== tab.id"
        >
            <slot :name="tab.id" />
        </div>
    </div>
</template>
