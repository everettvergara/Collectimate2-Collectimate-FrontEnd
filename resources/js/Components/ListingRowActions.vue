<script setup>
import { Button } from '@/Components/ui/button';
import { Link } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from '@lucide/vue';

defineProps({
    viewHref: { type: String, default: null },
    editHref: { type: String, default: null },
    onEdit: { type: Function, default: null },
    onDelete: { type: Function, default: null },
});
</script>

<template>
    <div class="inline-flex items-center gap-1">
        <Link
            v-if="viewHref"
            :href="viewHref"
            class="inline-flex items-center justify-center rounded-md p-1.5 hover:bg-muted"
            style="color: var(--color-primary)"
            title="View"
            aria-label="View"
        >
            <Eye class="size-4" />
        </Link>
        <Link
            v-if="editHref"
            :href="editHref"
            class="inline-flex items-center justify-center rounded-md p-1.5 hover:bg-muted"
            style="color: var(--color-primary)"
            title="Edit"
            aria-label="Edit"
        >
            <Pencil class="size-4" />
        </Link>
        <Button
            v-else-if="onEdit"
            type="button"
            variant="ghost"
            size="icon"
            class="size-8"
            style="color: var(--color-primary)"
            title="Edit"
            aria-label="Edit"
            @click="onEdit"
        >
            <Pencil class="size-4" />
        </Button>
        <Button
            v-if="onDelete"
            type="button"
            variant="ghost"
            size="icon"
            class="size-8 text-destructive hover:text-destructive"
            title="Delete"
            aria-label="Delete"
            @click="onDelete"
        >
            <Trash2 class="size-4" />
        </Button>
    </div>
</template>
