<script setup>
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <div class="form-label mb-1">Update password</div>
        <p class="text-sm mb-4" style="color: var(--color-text-muted)">
            Use a long, unique password for this account.
        </p>

        <form class="space-y-4" @submit.prevent="updatePassword">
            <div>
                <label class="form-label block mb-1" for="current_password">Current password</label>
                <Input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="w-full"
                    autocomplete="current-password"
                />
                <InputError class="mt-1" :message="form.errors.current_password" />
            </div>

            <div>
                <label class="form-label block mb-1" for="password">New password</label>
                <Input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="w-full"
                    autocomplete="new-password"
                />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div>
                <label class="form-label block mb-1" for="password_confirmation">Confirm password</label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="w-full"
                    autocomplete="new-password"
                />
                <InputError class="mt-1" :message="form.errors.password_confirmation" />
            </div>

            <Button type="submit" :disabled="form.processing">Save</Button>
        </form>
    </section>
</template>
