<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ entity: Object });

const previewUrl = ref(props.entity?.logo_url ?? null);
const fileInputRef = ref(null);

const form = useForm({
    entity_code: props.entity?.entity_code ?? '',
    name: props.entity?.name ?? '',
    logo: null,
});

function onLogoChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.logo = file;
    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    }
}

const submit = () => {
    const options = {
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
            if (fileInputRef.value) fileInputRef.value.value = '';
        },
    };

    if (props.entity) form.put(route('entities.update', props.entity.id), options);
    else form.post(route('entities.store'), options);
};
</script>

<template>
    <Head :title="entity ? 'Edit Entity' : 'Create Entity'" />
    <AppLayout>
        <template #header>{{ entity ? 'Edit Entity' : 'Create Entity' }}</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div class="flex items-center gap-4">
                <div
                    class="h-16 w-16 rounded-md overflow-hidden shrink-0 flex items-center justify-center border text-sm font-medium"
                    style="background: var(--color-bg-surface); border-color: var(--color-border); color: var(--color-text-muted)"
                >
                    <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Entity logo"
                        class="h-full w-full object-contain"
                    />
                    <span v-else>{{ (form.name || '?').slice(0, 1).toUpperCase() }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <label class="form-label block mb-1">Logo</label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm"
                        @change="onLogoChange"
                    />
                    <InputError class="mt-1" :message="form.errors.logo" />
                </div>
            </div>
            <div>
                <label class="form-label block mb-1">Entity code</label>
                <Input v-model="form.entity_code" class="w-full" />
                <InputError :message="form.errors.entity_code" />
            </div>
            <div>
                <label class="form-label block mb-1">Name</label>
                <Input v-model="form.name" class="w-full" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">Save</Button>
                <Link :href="entity ? route('entities.show', entity.id) : route('entities.index')">
                    <Button type="button" variant="secondary">Cancel</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
