<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Icon.vue';
import { formatDate } from '../../lib/format';

const props = defineProps({ settings: Object, users: Array, aiKeyConfigured: Boolean, aiModel: String });

const form = useForm({
    ...props.settings,
    allowed_domains: props.settings.allowed_domains.join(', '),
    distribution_list: (props.settings.distribution_list || []).join(', '),
});
const list = (s) => s.split(/[\s,;]+/).map((x) => x.trim()).filter(Boolean);
function save() {
    form.transform((d) => ({ ...d, allowed_domains: list(d.allowed_domains), distribution_list: list(d.distribution_list) }))
        .put('/settings', { preserveScroll: true });
}
const firstError = (prefix) => Object.entries(form.errors).find(([k]) => k.startsWith(prefix))?.[1];

const newUser = useForm({ name: '', email: '', role: 'reviewer' });
function addUser() { newUser.post('/users', { preserveScroll: true, onSuccess: () => newUser.reset() }); }
const userErrors = ref({});
function updateUser(u, patch) {
    router.put(`/users/${u.id}`, { role: u.role, active: u.active, ...patch }, { preserveScroll: true, onError: (e) => (userErrors.value = e) });
}
</script>

<template>
    <AppLayout title="Settings">
        <h1 class="text-2xl font-semibold sm:text-[28px]">Settings</h1>
        <p class="text-ink-muted">Organisation-wide settings and staff access.</p>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <section class="glass glass-strong p-5 sm:p-6">
                <h2 class="text-lg font-semibold">General</h2>
                <form class="mt-4 space-y-4" @submit.prevent="save">
                    <div>
                        <label for="s-dom" class="label">Allowed staff domains</label>
                        <input id="s-dom" v-model="form.allowed_domains" class="field font-mono" :aria-invalid="!!firstError('allowed_domains')" />
                        <p class="hint">Comma-separated. Only Google accounts on these domains can sign in.</p>
                        <p v-if="firstError('allowed_domains')" class="error">{{ firstError('allowed_domains') }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="s-exp" class="label">Default invitation expiry (days)</label>
                            <input id="s-exp" v-model.number="form.default_expiry_days" type="number" min="1" max="90" class="field font-mono" />
                            <p v-if="form.errors.default_expiry_days" class="error">{{ form.errors.default_expiry_days }}</p>
                        </div>
                        <div>
                            <label for="s-ret" class="label">Data retention (years after decision)</label>
                            <input id="s-ret" v-model.number="form.retention_years" type="number" min="1" max="10" class="field font-mono" />
                            <p v-if="form.errors.retention_years" class="error">{{ form.errors.retention_years }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="s-sender" class="label">Email sender name</label>
                            <input id="s-sender" v-model="form.sender_name" class="field" />
                            <p v-if="form.errors.sender_name" class="error">{{ form.errors.sender_name }}</p>
                        </div>
                        <div>
                            <label for="s-reply" class="label">Reply-to address</label>
                            <input id="s-reply" v-model="form.reply_to" type="email" class="field" />
                            <p v-if="form.errors.reply_to" class="error">{{ form.errors.reply_to }}</p>
                        </div>
                    </div>
                    <div>
                        <label for="s-dist" class="label">Submission notification list</label>
                        <input id="s-dist" v-model="form.distribution_list" class="field" placeholder="procurement@hexamatics.com" />
                        <p class="hint">Also emailed when a vendor submits, in addition to the inviting reviewer.</p>
                        <p v-if="firstError('distribution_list')" class="error">{{ firstError('distribution_list') }}</p>
                    </div>
                    <div class="panel p-4">
                        <label class="flex items-center gap-2 font-medium"><input v-model="form.ai_scoring" type="checkbox" class="h-4 w-4 accent-[var(--hexa-accent)]" />AI-suggested scoring for written answers</label>
                        <p class="hint">Model <span class="font-mono">{{ aiModel }}</span>. Only the question, guide note and answer are sent — never contact details or PAN. A reviewer always saves the final points.</p>
                        <p v-if="!aiKeyConfigured" class="mt-2 flex items-center gap-1.5 text-xs text-review"><Icon name="alert" :size="14" />ANTHROPIC_API_KEY is not set on the server.</p>
                    </div>
                    <button class="btn btn-primary" :disabled="form.processing">Save settings</button>
                </form>
            </section>

            <section class="glass glass-strong p-5 sm:p-6">
                <h2 class="text-lg font-semibold">Staff</h2>
                <form class="mt-4 grid gap-2 sm:grid-cols-[1fr_1.3fr_auto_auto]" @submit.prevent="addUser">
                    <input v-model="newUser.name" class="field" placeholder="Name" aria-label="Name" />
                    <input v-model="newUser.email" type="email" class="field" placeholder="name@hexamatics.com" aria-label="Email" />
                    <select v-model="newUser.role" class="field" aria-label="Role"><option value="admin">Admin</option><option value="reviewer">Reviewer</option><option value="viewer">Viewer</option></select>
                    <button class="btn btn-secondary" :disabled="newUser.processing"><Icon name="plus" :size="16" />Add</button>
                </form>
                <p v-for="(e, k) in newUser.errors" :key="k" class="error">{{ e }}</p>
                <p v-for="(e, k) in userErrors" :key="k" class="error">{{ e }}</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="table-glass">
                        <thead><tr><th>User</th><th>Role</th><th>Active</th></tr></thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id">
                                <td><div class="font-medium">{{ u.name }}</div><div class="text-xs text-ink-muted">{{ u.email }} · last sign-in {{ formatDate(u.last_login_at) }}</div></td>
                                <td>
                                    <select :value="u.role" class="field !min-h-[36px] w-32 text-sm" :aria-label="`Role for ${u.name}`" @change="updateUser(u, { role: $event.target.value })">
                                        <option value="admin">Admin</option><option value="reviewer">Reviewer</option><option value="viewer">Viewer</option>
                                    </select>
                                </td>
                                <td><input type="checkbox" :checked="u.active" class="h-4 w-4 accent-[var(--hexa-accent)]" :aria-label="`${u.name} active`" @change="updateUser(u, { active: $event.target.checked })" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="hint mt-3">Staff sign in with Google Workspace; MFA is enforced by Google. Deactivated users are signed out on their next request.</p>
            </section>
        </div>
    </AppLayout>
</template>
