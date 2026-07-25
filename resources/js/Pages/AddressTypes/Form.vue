<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ addressType: Object });
const form = useForm({
    name: props.addressType?.name ?? '',
    code: props.addressType?.code ?? '',
    sort_order: props.addressType?.sort_order ?? 0,
    is_active: props.addressType?.is_active ?? true,
});

const submit = () => {
    if (props.addressType) form.put(route('address-types.update', props.addressType.id));
    else form.post(route('address-types.store'));
};
</script>

<template>
    <Head :title="addressType ? 'Edit Address Type' : 'Create Address Type'" />
    <AppLayout>
        <template #header>{{ addressType ? 'Edit Address Type' : 'Create Address Type' }}</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div>
                <label class="form-label block mb-1">Name</label>
                <Input v-model="form.name" class="w-full" />
                <InputError :message="form.errors.name" />
            </div>
            <div>
                <label class="form-label block mb-1">Code</label>
                <Input v-model="form.code" class="w-full" :disabled="!!addressType?.is_default" />
                <InputError :message="form.errors.code" />
            </div>
            <div>
                <label class="form-label block mb-1">Sort order</label>
                <Input v-model="form.sort_order" type="number" class="w-full" />
            </div>
            <label class="flex items-center gap-2">
                <Checkbox v-model="form.is_active" />
                <span>Active</span>
            </label>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">Save</Button>
                <Link :href="route('address-types.index')">
                    <Button type="button" variant="secondary">Cancel</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
