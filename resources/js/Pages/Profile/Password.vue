<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Change password" />
    <AppLayout>
        <template #header>Change password</template>

        <div class="max-w-xl space-y-4">
            <div>
                <Link :href="route('profile.edit')">
                    <Button type="button" variant="secondary" size="sm">Back to profile</Button>
                </Link>
            </div>

            <section class="p-4 border rounded" style="border-color: var(--color-border); background: var(--color-bg-surface)">
                <UpdatePasswordForm />
            </section>
        </div>
    </AppLayout>
</template>
