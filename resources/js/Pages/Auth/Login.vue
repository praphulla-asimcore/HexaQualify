<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import HexaLockup from '../../Components/HexaLockup.vue';
import ThemeToggle from '../../Components/ThemeToggle.vue';
import Toasts from '../../Components/Toasts.vue';
import { toast } from '../../lib/toast';

defineProps({ devLogin: Boolean, googleConfigured: Boolean });
const page = usePage();
const dev = useForm({ email: 'admin@hexamatics.com' });
watch(() => page.props.flash?.error, (e) => e && toast(e, 'error'), { immediate: true });
</script>

<template>
    <Head title="Sign in" />
    <div class="absolute right-4 top-4"><ThemeToggle /></div>
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="glass rise w-full max-w-md p-8 sm:p-10">
            <HexaLockup :height="28" />
            <h1 class="mt-8 text-2xl font-semibold">Sign in</h1>
            <p class="mt-1 text-ink-muted">Pre-pitch vendor screening for Hexamatics staff.</p>

            <a href="/auth/google/redirect" class="btn btn-primary mt-8 w-full" :class="{ 'pointer-events-none opacity-60': !googleConfigured }" :aria-disabled="!googleConfigured">
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#fff" d="M44.5 20H24v8.5h11.8C34.7 33.9 30.1 37 24 37c-7.2 0-13-5.8-13-13s5.8-13 13-13c3.1 0 5.9 1.1 8.1 2.9l6.4-6.4C34.6 4.1 29.6 2 24 2 11.8 2 2 11.8 2 24s9.8 22 22 22c11 0 21-8 21-22 0-1.3-.2-2.7-.5-4z"/></svg>
                Continue with Google Workspace
            </a>
            <p v-if="!googleConfigured" class="hint">Google sign-in is not configured yet (set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET).</p>
            <p class="hint mt-3 text-center">Use your @hexamatics.com account. Multi-factor authentication is enforced by Google.</p>

            <form v-if="devLogin" class="mt-8 border-t border-[var(--line)] pt-6" @submit.prevent="dev.post('/login/dev')">
                <label for="dev-email" class="label">Development sign-in</label>
                <div class="flex gap-2">
                    <select id="dev-email" v-model="dev.email" class="field">
                        <option value="admin@hexamatics.com">Admin</option>
                        <option value="reviewer@hexamatics.com">Reviewer</option>
                        <option value="viewer@hexamatics.com">Viewer</option>
                    </select>
                    <button class="btn btn-secondary" :disabled="dev.processing">Sign in</button>
                </div>
                <p class="hint">Only shown when HQ_DEV_LOGIN=true outside production.</p>
            </form>
        </div>
    </main>
    <Toasts />
</template>
