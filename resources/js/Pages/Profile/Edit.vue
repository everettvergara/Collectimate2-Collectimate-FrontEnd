<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />
    <AppLayout>
        <template #header>Profile</template>

        <div class="max-w-2xl space-y-4">
            <section class="p-4 border rounded" style="border-color: var(--color-border); background: var(--color-bg-surface)">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </section>

            <section class="p-4 border rounded flex items-center justify-between gap-3" style="border-color: var(--color-border); background: var(--color-bg-surface)">
                <div>
                    <div class="form-label mb-1">Password</div>
                    <p class="text-sm" style="color: var(--color-text-muted)">
                        Change your account password on a separate page.
                    </p>
                </div>
                <Link :href="route('profile.password.edit')">
                    <Button type="button" variant="secondary" size="sm">Change password</Button>
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
