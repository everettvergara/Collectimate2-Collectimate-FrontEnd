<script setup>
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = page.props.auth.user;
const previewUrl = ref(user.avatar_url ?? null);
const fileInputRef = ref(null);

const form = useForm({
    name: user.name ?? '',
    email: user.email ?? '',
    about_me: user.about_me ?? '',
    avatar: null,
});

function onAvatarChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.avatar = file;
    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    }
}

function submit() {
    form.patch(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.avatar = null;
            if (fileInputRef.value) fileInputRef.value.value = '';
            previewUrl.value = page.props.auth.user?.avatar_url ?? null;
        },
    });
}
</script>

<template>
    <section>
        <div class="form-label mb-3">Profile information</div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="flex items-center gap-4">
                <div
                    class="h-16 w-16 rounded-full overflow-hidden shrink-0 flex items-center justify-center text-sm font-medium"
                    style="background: var(--color-primary); color: #fff"
                >
                    <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Avatar"
                        class="h-full w-full object-cover"
                    />
                    <span v-else>{{ (form.name || '?').slice(0, 1).toUpperCase() }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <label class="form-label block mb-1">Profile photo</label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm"
                        @change="onAvatarChange"
                    />
                    <InputError class="mt-1" :message="form.errors.avatar" />
                </div>
            </div>

            <div>
                <label class="form-label block mb-1" for="name">Name</label>
                <Input id="name" v-model="form.name" class="w-full" required autocomplete="name" />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <label class="form-label block mb-1" for="email">Email</label>
                <Input id="email" v-model="form.email" type="email" class="w-full" required autocomplete="username" />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div>
                <label class="form-label block mb-1" for="about_me">About me</label>
                <Textarea id="about_me" v-model="form.about_me" class="w-full min-h-28" rows="4" />
                <InputError class="mt-1" :message="form.errors.about_me" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-sm" style="color: var(--color-text-muted)">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="underline"
                        style="color: var(--color-primary)"
                    >
                        Re-send verification email
                    </Link>
                </p>
                <p
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm"
                    style="color: var(--color-success, #16a34a)"
                >
                    A new verification link has been sent.
                </p>
            </div>

            <Button type="submit" :disabled="form.processing">Save</Button>
        </form>
    </section>
</template>
