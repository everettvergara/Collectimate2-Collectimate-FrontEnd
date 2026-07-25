<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ profile: Object, users: Array, statuses: Array });

const form = useForm({
    employee_number: props.profile?.employee_number ?? '',
    first_name: props.profile?.first_name ?? '',
    last_name: props.profile?.last_name ?? '',
    display_name: props.profile?.display_name ?? '',
    position: props.profile?.position ?? '',
    department: props.profile?.department ?? '',
    mobile: props.profile?.mobile ?? '',
    email: props.profile?.email ?? '',
    status: props.profile?.status ?? 'active',
    notes: props.profile?.notes ?? '',
    user_id: props.profile?.user_id ?? null,
});

const submit = () => {
    if (props.profile) form.put(route('agent-profiles.update', props.profile.id));
    else form.post(route('agent-profiles.store'));
};
</script>

<template>
    <Head :title="profile ? 'Edit Agent Profile' : 'Create Agent Profile'" />
    <AppLayout>
        <template #header>{{ profile ? 'Edit Agent Profile' : 'Create Agent Profile' }}</template>
        <form class="max-w-xl space-y-4" @submit.prevent="submit">
            <div>
                <label class="form-label block mb-1">Employee #</label>
                <Input v-model="form.employee_number" class="w-full" />
                <InputError :message="form.errors.employee_number" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label block mb-1">First name</label>
                    <Input v-model="form.first_name" class="w-full" />
                </div>
                <div>
                    <label class="form-label block mb-1">Last name</label>
                    <Input v-model="form.last_name" class="w-full" />
                </div>
            </div>
            <div>
                <label class="form-label block mb-1">Display name</label>
                <Input v-model="form.display_name" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Position</label>
                <Input v-model="form.position" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Department</label>
                <Input v-model="form.department" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Email</label>
                <Input v-model="form.email" type="email" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Mobile</label>
                <Input v-model="form.mobile" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Status</label>
                <Select v-model="form.status" :options="statuses" class="w-full" />
            </div>
            <div>
                <label class="form-label block mb-1">Linked user</label>
                <Select v-model="form.user_id" :options="users" option-label="username" option-value="id" class="w-full" show-clear placeholder="Optional" />
            </div>
            <div>
                <label class="form-label block mb-1">Notes</label>
                <Textarea v-model="form.notes" rows="3" class="w-full" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">Save</Button>
                <Link :href="route('agent-profiles.index')">
                    <Button type="button" variant="secondary">Cancel</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
