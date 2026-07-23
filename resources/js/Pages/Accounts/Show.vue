<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';

const props = defineProps({ account: Object, contactTypes: Array, can: Object });

const contactForm = useForm({ type: 'email', value: '', label: '', is_primary: false, notes: '' });
const addressForm = useForm({ type: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: '', is_primary: false });
const secondaryForm = useForm({ name: '', relationship: '', phone: '', email: '', notes: '' });
const socialForm = useForm({ platform: '', url: '', label: '' });

function submitContact() { contactForm.post(route('accounts.contact-infos.store', props.account.id), { onSuccess: () => contactForm.reset() }); }
function submitAddress() { addressForm.post(route('accounts.addresses.store', props.account.id), { onSuccess: () => addressForm.reset() }); }
function submitSecondary() { secondaryForm.post(route('accounts.secondary-contacts.store', props.account.id), { onSuccess: () => secondaryForm.reset() }); }
function submitSocial() { socialForm.post(route('accounts.social-links.store', props.account.id), { onSuccess: () => socialForm.reset() }); }

function removeContact(id) { router.delete(route('accounts.contact-infos.destroy', [props.account.id, id])); }
function removeAddress(id) { router.delete(route('accounts.addresses.destroy', [props.account.id, id])); }
function removeSecondary(id) { router.delete(route('accounts.secondary-contacts.destroy', [props.account.id, id])); }
function removeSocial(id) { router.delete(route('accounts.social-links.destroy', [props.account.id, id])); }
</script>

<template>
    <Head :title="account.account_number" />
    <AppLayout>
        <template #header>{{ account.account_number }}</template>
        <div class="space-y-4">
            <div class="p-4 border rounded max-w-3xl" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div><div class="form-label">Entity</div><div>{{ account.campaign?.entity?.name ?? '—' }}</div></div>
                    <div><div class="form-label">Campaign</div><div>{{ account.campaign?.name }}</div></div>
                    <div><div class="form-label">Product</div><div>{{ account.product ?? '—' }}</div></div>
                    <div><div class="form-label">Balance</div><div>{{ account.balance }}</div></div>
                    <div><div class="form-label">Status</div><div>{{ account.status?.name ?? '—' }}</div></div>
                </div>
                <Link v-if="can.update" :href="route('accounts.edit', account.id)" class="inline-block mt-4"><Button label="Edit" size="small" /></Link>
            </div>

            <section class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-3">Contact info</div>
                <ul class="space-y-2 mb-4">
                    <li v-for="c in account.contact_infos" :key="c.id" class="flex justify-between">
                        <span>{{ c.type }}: {{ c.value }} <span v-if="c.is_primary" style="color: var(--color-text-muted)">(primary)</span></span>
                        <Button v-if="can.update" label="Delete" text severity="danger" size="small" @click="removeContact(c.id)" />
                    </li>
                    <li v-if="!account.contact_infos?.length" style="color: var(--color-text-muted)">None</li>
                </ul>
                <form v-if="can.update" class="grid md:grid-cols-4 gap-2 items-end" @submit.prevent="submitContact">
                    <Select v-model="contactForm.type" :options="contactTypes" class="w-full" />
                    <InputText v-model="contactForm.value" placeholder="Value" class="w-full" />
                    <InputText v-model="contactForm.label" placeholder="Label" class="w-full" />
                    <Button type="submit" label="Add" size="small" :loading="contactForm.processing" />
                </form>
            </section>

            <section class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-3">Addresses</div>
                <ul class="space-y-2 mb-4">
                    <li v-for="a in account.addresses" :key="a.id" class="flex justify-between">
                        <span>{{ a.line1 }}, {{ a.city }} {{ a.postal_code }}</span>
                        <Button v-if="can.update" label="Delete" text severity="danger" size="small" @click="removeAddress(a.id)" />
                    </li>
                    <li v-if="!account.addresses?.length" style="color: var(--color-text-muted)">None</li>
                </ul>
                <form v-if="can.update" class="grid md:grid-cols-3 gap-2 items-end" @submit.prevent="submitAddress">
                    <InputText v-model="addressForm.line1" placeholder="Line 1" class="w-full" />
                    <InputText v-model="addressForm.city" placeholder="City" class="w-full" />
                    <Button type="submit" label="Add" size="small" :loading="addressForm.processing" />
                </form>
            </section>

            <section class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-3">Secondary contacts</div>
                <ul class="space-y-2 mb-4">
                    <li v-for="s in account.secondary_contacts" :key="s.id" class="flex justify-between">
                        <span>{{ s.name }} — {{ s.phone ?? s.email }}</span>
                        <Button v-if="can.update" label="Delete" text severity="danger" size="small" @click="removeSecondary(s.id)" />
                    </li>
                    <li v-if="!account.secondary_contacts?.length" style="color: var(--color-text-muted)">None</li>
                </ul>
                <form v-if="can.update" class="grid md:grid-cols-4 gap-2 items-end" @submit.prevent="submitSecondary">
                    <InputText v-model="secondaryForm.name" placeholder="Name" class="w-full" />
                    <InputText v-model="secondaryForm.phone" placeholder="Phone" class="w-full" />
                    <InputText v-model="secondaryForm.email" placeholder="Email" class="w-full" />
                    <Button type="submit" label="Add" size="small" :loading="secondaryForm.processing" />
                </form>
            </section>

            <section class="p-4 border rounded" style="background: var(--color-bg-surface); border-color: var(--color-border)">
                <div class="form-label mb-3">Social links</div>
                <ul class="space-y-2 mb-4">
                    <li v-for="s in account.social_links" :key="s.id" class="flex justify-between">
                        <a :href="s.url" target="_blank" class="hover:underline" style="color: var(--color-primary)">{{ s.platform }}: {{ s.url }}</a>
                        <Button v-if="can.update" label="Delete" text severity="danger" size="small" @click="removeSocial(s.id)" />
                    </li>
                    <li v-if="!account.social_links?.length" style="color: var(--color-text-muted)">None</li>
                </ul>
                <form v-if="can.update" class="grid md:grid-cols-3 gap-2 items-end" @submit.prevent="submitSocial">
                    <InputText v-model="socialForm.platform" placeholder="Platform" class="w-full" />
                    <InputText v-model="socialForm.url" placeholder="URL" class="w-full" />
                    <Button type="submit" label="Add" size="small" :loading="socialForm.processing" />
                </form>
            </section>
        </div>
    </AppLayout>
</template>
