<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const props = defineProps({
    user: Object,
    roles: Array,
    statuses: Array,
});

const form = useForm({
    username: props.user?.username ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    first_name: props.user?.first_name ?? '',
    last_name: props.user?.last_name ?? '',
    mobile: props.user?.mobile ?? '',
    status: props.user?.status ?? 'active',
    role_id: props.user?.role_id ?? null,
});

const submit = () => {
    if (props.user) {
        form.put(route('users.update', props.user.id));
    } else {
        form.post(route('users.store'));
    }
};
</script>

<template>
    <Head :title="user ? 'Edit User' : 'Create User'" />
    <AppLayout>
        <template #header>{{ user ? 'Edit User' : 'Create User' }}</template>

        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div>
                <label class="form-label block mb-1">Username</label>
                <InputText v-model="form.username" class="w-full" />
                <InputError :message="form.errors.username" class="mt-1" />
            </div>
            <div>
                <label class="form-label block mb-1">Email</label>
                <InputText v-model="form.email" type="email" class="w-full" />
                <InputError :message="form.errors.email" class="mt-1" />
            </div>
            <div>
                <label class="form-label block mb-1">Password</label>
                <Password v-model="form.password" class="w-full" input-class="w-full" toggle-mask :feedback="false" />
                <InputError :message="form.errors.password" class="mt-1" />
            </div>
            <div>
                <label class="form-label block mb-1">Confirm password</label>
                <Password v-model="form.password_confirmation" class="w-full" input-class="w-full" toggle-mask :feedback="false" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label block mb-1">First name</label>
                    <InputText v-model="form.first_name" class="w-full" />
                </div>
                <div>
                    <label class="form-label block mb-1">Last name</label>
                    <InputText v-model="form.last_name" class="w-full" />
                </div>
            </div>
            <div>
                <label class="form-label block mb-1">Mobile</label>
                <InputText v-model="form.mobile" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Status</label>
                <Select v-model="form.status" :options="statuses" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Role</label>
                <Select v-model="form.role_id" :options="roles" option-label="name" option-value="id" class="w-full" placeholder="Select role" />
                <InputError :message="form.errors.role_id" class="mt-1" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" label="Save" :loading="form.processing" />
                <Link :href="route('users.index')">
                    <Button type="button" label="Cancel" severity="secondary" />
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
