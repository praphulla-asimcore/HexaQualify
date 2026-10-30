<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusChip from '../../Components/StatusChip.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import Icon from '../../Components/Icon.vue';
import { api, formatDate, STATUS_LABELS } from '../../lib/format';
import { toast } from '../../lib/toast';

const props = defineProps({ rows: Array, filters: Object, questionnaires: Array, defaultExpiry: Number, statuses: Array, can: Object });
const page = usePage();
const f = reactive({ ...props.filters });
let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get('/invitations', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 300);
});

// Create
const createOpen = ref(new URLSearchParams(location.search).has('new'));
const form = useForm({
    vendor_company: '', contact_name: '', contact_email: '', questionnaire_id: props.questionnaires[0]?.id ?? '',
    expiry_days: props.defaultExpiry, note: '', notify_result: false, confirm_duplicate: false,
});
const duplicates = ref(0);
async function checkDuplicate() {
    if (!form.contact_email || !form.questionnaire_id || !/@/.test(form.contact_email)) return (duplicates.value = 0);
    try {
        duplicates.value = (await api('/invitations/check', { method: 'POST', body: { contact_email: form.contact_email, questionnaire_id: form.questionnaire_id } })).open;
    } catch { duplicates.value = 0; }
}
watch(() => form.questionnaire_id, checkDuplicate);
function create() {
    form.post('/invitations', {
        preserveScroll: true,
        onSuccess: () => { createOpen.value = false; form.reset(); duplicates.value = 0; },
    });
}

// Link shown once after create / resend (tokens are stored hashed and cannot be shown again).
const link = ref(null);
watch(() => page.props.flash?.link, (l) => { if (l) link.value = l; }, { immediate: true });
async function copy(text) {
    try { await navigator.clipboard.writeText(text); toast('Link copied.'); } catch { toast('Copy failed — select the link and copy it manually.', 'error'); }
}

// Row actions
const confirm = ref(null); // { kind, row }
const extendDays = ref(7);
const openRows = ['draft', 'sent', 'opened', 'in_progress'];
function run() {
    const { kind, row } = confirm.value;
    const opts = { preserveScroll: true, onFinish: () => (confirm.value = null) };
    if (kind === 'resend') router.post(`/invitations/${row.id}/resend`, {}, opts);
    if (kind === 'revoke') router.post(`/invitations/${row.id}/revoke`, {}, opts);
    if (kind === 'extend') router.post(`/invitations/${row.id}/extend`, { days: extendDays.value }, opts);
}
const confirmCopy = computed(() => ({
    resend: { title: 'Resend invitation?', text: 'A new link will be emailed. The previous link stops working immediately.', btn: 'Resend' },
    revoke: { title: 'Revoke invitation?', text: 'The vendor will no longer be able to open or submit the questionnaire. This cannot be undone.', btn: 'Revoke', danger: true },
    extend: { title: 'Extend expiry', text: 'Add days to the deadline. Expired links become usable again.', btn: 'Extend' },
}[confirm.value?.kind] ?? {}));
</script>

<template>
    <AppLayout title="Invitations">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold sm:text-[28px]">Invitations</h1>
                <p class="text-ink-muted">Send vendors their screening questionnaire through a secure, personal link.</p>
            </div>
            <button class="btn btn-primary" @click="createOpen = true"><Icon name="plus" :size="16" />New invitation</button>
        </div>

        <div v-if="link" class="glass rise mt-5 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
            <Icon name="check" class="shrink-0 text-qualified" />
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold">Invitation link (shown once)</div>
                <div class="truncate font-mono text-xs text-ink-muted" :title="link">{{ link }}</div>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-secondary btn-sm" @click="copy(link)"><Icon name="copy" :size="14" />Copy link</button>
                <button class="btn btn-ghost btn-sm" @click="link = null" aria-label="Dismiss"><Icon name="x" :size="14" /></button>
            </div>
        </div>

        <section class="glass mt-5 grid gap-3 p-4 sm:grid-cols-[2fr_1fr]">
            <div class="relative">
                <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
                <input v-model="f.q" type="search" class="field !pl-9" placeholder="Search vendor or email" aria-label="Search" />
            </div>
            <select v-model="f.status" class="field" aria-label="Status">
                <option :value="null">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
            </select>
        </section>

        <section class="mt-4">
            <EmptyState v-if="!rows.length" title="No invitations yet" text="Invite a vendor to send them the right questionnaire for their service category.">
                <button class="btn btn-primary" @click="createOpen = true"><Icon name="plus" :size="16" />New invitation</button>
            </EmptyState>
            <div v-else class="glass glass-strong overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table-glass min-w-[880px]">
                        <thead><tr><th>Vendor</th><th>Questionnaire</th><th>Status</th><th>Expires</th><th>Invited by</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            <tr v-for="r in rows" :key="r.id">
                                <td>
                                    <div class="font-semibold">{{ r.vendor_company }}</div>
                                    <div class="text-xs text-ink-muted">{{ r.contact_name }} · {{ r.contact_email }}</div>
                                    <div v-if="r.note" class="mt-1 max-w-xs truncate text-xs text-ink-muted" :title="r.note">Note: {{ r.note }}</div>
                                </td>
                                <td>{{ r.questionnaire }} <span class="font-mono text-xs text-ink-muted">v{{ r.version }}</span></td>
                                <td><StatusChip :status="r.status" /></td>
                                <td class="whitespace-nowrap">{{ formatDate(r.expires_at) }}</td>
                                <td class="text-sm">{{ r.created_by ?? '—' }}<div class="text-xs text-ink-muted">{{ formatDate(r.sent_at) }}</div></td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        <Link v-if="r.response_id" :href="`/responses/${r.response_id}`" class="btn btn-secondary btn-sm">View response</Link>
                                        <template v-else>
                                            <button v-if="openRows.includes(r.status)" class="btn btn-ghost btn-sm" @click="confirm = { kind: 'resend', row: r }"><Icon name="mail" :size="14" />Resend</button>
                                            <button v-if="[...openRows, 'expired'].includes(r.status)" class="btn btn-ghost btn-sm" @click="confirm = { kind: 'extend', row: r }"><Icon name="clock" :size="14" />Extend</button>
                                            <button v-if="[...openRows, 'expired'].includes(r.status)" class="btn btn-danger btn-sm" @click="confirm = { kind: 'revoke', row: r }">Revoke</button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <Modal :open="createOpen" title="New invitation" @close="createOpen = false">
            <form class="space-y-4" novalidate @submit.prevent="create">
                <div>
                    <label for="i-company" class="label">Vendor company <span class="text-notq">*</span></label>
                    <input id="i-company" v-model="form.vendor_company" class="field" required :aria-invalid="!!form.errors.vendor_company" />
                    <p v-if="form.errors.vendor_company" class="error">{{ form.errors.vendor_company }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="i-name" class="label">Contact name <span class="text-notq">*</span></label>
                        <input id="i-name" v-model="form.contact_name" class="field" required :aria-invalid="!!form.errors.contact_name" />
                        <p v-if="form.errors.contact_name" class="error">{{ form.errors.contact_name }}</p>
                    </div>
                    <div>
                        <label for="i-email" class="label">Contact email <span class="text-notq">*</span></label>
                        <input id="i-email" v-model.trim="form.contact_email" type="email" class="field" required @blur="checkDuplicate" :aria-invalid="!!form.errors.contact_email" />
                        <p v-if="form.errors.contact_email" class="error">{{ form.errors.contact_email }}</p>
                    </div>
                </div>
                <div>
                    <label for="i-q" class="label">Questionnaire (service category) <span class="text-notq">*</span></label>
                    <select id="i-q" v-model="form.questionnaire_id" class="field">
                        <option v-for="q in questionnaires" :key="q.id" :value="q.id">{{ q.name }} — {{ q.category }}</option>
                    </select>
                    <p v-if="form.errors.questionnaire_id" class="error">{{ form.errors.questionnaire_id }}</p>
                </div>
                <div v-if="duplicates > 0" class="flex items-start gap-2 rounded-[var(--radius-sm)] bg-review-bg p-3 text-sm" role="alert">
                    <Icon name="alert" class="mt-0.5 shrink-0 text-review" />
                    <div>
                        This email already has {{ duplicates }} open invitation{{ duplicates > 1 ? 's' : '' }} for this questionnaire.
                        <label class="mt-2 flex items-center gap-2 font-medium"><input v-model="form.confirm_duplicate" type="checkbox" class="accent-[var(--hexa-accent)]" />Send anyway</label>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="i-exp" class="label">Expires in (days)</label>
                        <input id="i-exp" v-model.number="form.expiry_days" type="number" min="1" max="90" class="field font-mono" />
                        <p v-if="form.errors.expiry_days" class="error">{{ form.errors.expiry_days }}</p>
                    </div>
                    <label class="mt-0 flex items-center gap-2 self-end pb-3 text-sm sm:mt-7">
                        <input v-model="form.notify_result" type="checkbox" class="h-4 w-4 accent-[var(--hexa-accent)]" />Email the vendor the outcome
                    </label>
                </div>
                <div>
                    <label for="i-note" class="label">Internal note</label>
                    <textarea id="i-note" v-model="form.note" rows="2" class="field" placeholder="Visible to staff only"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-ghost" @click="createOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing || (duplicates > 0 && !form.confirm_duplicate)">{{ form.processing ? 'Sending…' : 'Send invitation' }}</button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!confirm" :title="confirmCopy.title" @close="confirm = null">
            <p class="text-sm text-ink-muted"><strong class="text-ink">{{ confirm?.row.vendor_company }}</strong> — {{ confirmCopy.text }}</p>
            <div v-if="confirm?.kind === 'extend'" class="mt-4">
                <label for="x-days" class="label">Days to add</label>
                <input id="x-days" v-model.number="extendDays" type="number" min="1" max="90" class="field w-32 font-mono" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button class="btn btn-ghost" @click="confirm = null">Cancel</button>
                <button :class="confirmCopy.danger ? 'btn btn-danger' : 'btn btn-primary'" @click="run">{{ confirmCopy.btn }}</button>
            </div>
        </Modal>
    </AppLayout>
</template>
