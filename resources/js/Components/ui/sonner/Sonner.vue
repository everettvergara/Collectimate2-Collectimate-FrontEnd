<script setup>
import 'vue-sonner/style.css';
import { Toaster as Sonner } from 'vue-sonner';

const props = defineProps({
    class: { type: [String, Object, Array], default: undefined },
    theme: { type: String, default: 'light' },
    position: { type: String, default: 'top-right' },
    richColors: { type: Boolean, default: true },
    closeButton: { type: Boolean, default: true },
});
</script>

<template>
    <Sonner
        :theme="theme"
        :position="position"
        :rich-colors="richColors"
        :close-button="closeButton"
        :class="props.class"
        :toast-options="{
            classes: {
                toast: 'group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg',
                description: 'group-[.toast]:text-muted-foreground',
                actionButton: 'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
                cancelButton: 'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
            },
        }"
    />
</template>
