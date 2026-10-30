<script setup>
import { ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import HexaLockup from '../Components/HexaLockup.vue';
import KpiTile from '../Components/KpiTile.vue';
import ResultBadge from '../Components/ResultBadge.vue';
import ScoreBar from '../Components/ScoreBar.vue';
import StatusChip from '../Components/StatusChip.vue';
import EmptyState from '../Components/EmptyState.vue';
import Modal from '../Components/Modal.vue';
import Icon from '../Components/Icon.vue';
import { toast } from '../lib/toast';
import { toggleTheme, theme } from '../lib/theme';

const modal = ref(false);
const chip = ref('In Nepal');
const reduced = ref(false);
function toggleReduced() {
    reduced.value = !reduced.value;
    document.documentElement.toggleAttribute('data-transparency', reduced.value);
    if (reduced.value) document.documentElement.setAttribute('data-transparency', 'reduce');
}
const tokens = {
    Brand: ['--hexa-primary', '--hexa-secondary', '--hexa-accent'],
    Background: ['--bg-base', '--bg-blob-1', '--bg-blob-2', '--bg-blob-3'],
    Glass: ['--glass-bg', '--glass-bg-strong', '--glass-border', '--glass-highlight'],
    Text: ['--text', '--text-muted', '--text-on-brand'],
    Semantic: ['--qualified', '--qualified-bg', '--not-qualified', '--not-qualified-bg', '--review', '--review-bg'],
};
const value = (t) => getComputedStyle(document.documentElement).getPropertyValue(t).trim();
</script>

<template>
    <AppLayout title="Style guide">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold sm:text-[28px]">Style guide</h1>
                <p class="text-ink-muted">Every §10 token and component. Toggle the theme to review both.</p>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-secondary" @click="toggleTheme"><Icon :name="theme === 'dark' ? 'sun' : 'moon'" :size="16" />{{ theme === 'dark' ? 'Light' : 'Dark' }} theme</button>
                <button class="btn btn-ghost" @click="toggleReduced">{{ reduced ? 'Restore glass' : 'Simulate reduced transparency' }}</button>
            </div>
        </div>

        <section class="glass glass-strong mt-6 p-6">
            <h2 class="text-lg font-semibold">Logo lockup</h2>
            <div class="mt-4 flex flex-wrap items-center gap-8"><HexaLockup :height="28" /><HexaLockup :height="24" /><HexaLockup :height="24" :product="false" /></div>
            <p class="hint">Official file, unaltered. In dark theme the wordmark sits on a light plate to keep ≥ 4.5:1 contrast.</p>
        </section>

        <section class="glass glass-strong mt-4 p-6">
            <h2 class="text-lg font-semibold">Tokens</h2>
            <div v-for="(list, group) in tokens" :key="group" class="mt-4">
                <h3 class="text-sm font-semibold text-ink-muted">{{ group }}</h3>
                <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    <div v-for="t in list" :key="t + theme" class="panel overflow-hidden">
                        <div class="h-12" :style="{ background: `var(${t})` }"></div>
                        <div class="p-2"><div class="font-mono text-[11px]">{{ t }}</div><div class="font-mono text-[11px] text-ink-muted">{{ value(t) }}</div></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="glass glass-strong mt-4 p-6">
            <h2 class="text-lg font-semibold">Typography</h2>
            <p class="mt-3 font-display text-3xl font-bold">Sora 700 — Qualified to pitch</p>
            <p class="font-display text-xl font-semibold">Sora 600 — Section heading</p>
            <p class="mt-2">Plus Jakarta Sans 400 — Body text for questions and explanations.</p>
            <p class="font-medium">Plus Jakarta Sans 500 — Labels and UI.</p>
            <p class="font-mono">JetBrains Mono — PAN 601234567 · 83.7% · c9</p>
        </section>

        <section class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <KpiTile label="Total responses" :value="42" />
            <KpiTile label="Qualified" :value="18" tone="pass" />
            <KpiTile label="Not qualified" :value="15" tone="fail" />
            <KpiTile label="Needs review" :value="9" tone="review" />
        </section>

        <section class="glass glass-strong mt-4 grid gap-6 p-6 lg:grid-cols-2">
            <div>
                <h2 class="text-lg font-semibold">Result badges</h2>
                <div class="mt-3 flex flex-wrap gap-2"><ResultBadge result="Pass" /><ResultBadge result="Fail" /><ResultBadge result="Review" /><ResultBadge result="Pass" override /></div>
                <div class="mt-4 flex flex-wrap gap-3"><ResultBadge result="Pass" large /><ResultBadge result="Fail" large /><ResultBadge result="Review" large /></div>
                <h3 class="mt-6 text-sm font-semibold text-ink-muted">Invitation status</h3>
                <div class="mt-2 flex flex-wrap gap-2"><StatusChip v-for="s in ['sent', 'opened', 'in_progress', 'submitted', 'decided', 'expired', 'revoked']" :key="s" :status="s" /></div>
            </div>
            <div>
                <h2 class="text-lg font-semibold">Score bar</h2>
                <div class="mt-3 space-y-5"><ScoreBar :pct="83.7" :pass-mark="75" /><ScoreBar :pct="61.2" :pass-mark="70" /><ScoreBar :pct="92" :pass-mark="65" compact /></div>
            </div>
        </section>

        <section class="glass glass-strong mt-4 grid gap-6 p-6 lg:grid-cols-2">
            <div>
                <h2 class="text-lg font-semibold">Buttons</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button class="btn btn-primary">Primary</button>
                    <button class="btn btn-secondary">Secondary</button>
                    <button class="btn btn-ghost">Ghost</button>
                    <button class="btn btn-danger">Destructive</button>
                    <button class="btn btn-primary" disabled>Disabled</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button class="btn btn-secondary" @click="modal = true">Open modal</button>
                    <button class="btn btn-secondary" @click="toast('Saved. This is a toast.')">Show toast</button>
                    <button class="btn btn-secondary" @click="toast('Something went wrong.', 'error')">Error toast</button>
                </div>
            </div>
            <div>
                <h2 class="text-lg font-semibold">Inputs and chips</h2>
                <div class="mt-3 space-y-3">
                    <input class="field" placeholder="Text input (44 px touch target)" />
                    <input class="field" aria-invalid="true" value="Invalid value" aria-label="Invalid example" />
                    <p class="error">This question is required.</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="o in ['In Nepal', 'Outside Nepal, region named', 'Not specified']" :key="o" class="chip" :data-selected="chip === o">
                            <input v-model="chip" type="radio" :value="o" class="sr-only" /><Icon v-if="chip === o" name="check" :size="14" />{{ o }}
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="glass glass-strong mt-4 overflow-hidden">
            <table class="table-glass">
                <thead><tr><th>Vendor</th><th>Score</th><th>Result</th></tr></thead>
                <tbody>
                    <tr><td>Himal Payroll Pvt. Ltd.</td><td class="font-mono">83.7%</td><td><ResultBadge result="Pass" /></td></tr>
                    <tr><td>Kathmandu HR Cloud</td><td class="font-mono">92.2%</td><td><ResultBadge result="Fail" /></td></tr>
                    <tr><td>Everest Cloud Services</td><td class="font-mono">91.2%</td><td><ResultBadge result="Review" /></td></tr>
                </tbody>
            </table>
        </section>

        <div class="mt-4"><EmptyState title="No responses yet" text="Responses appear here as soon as a vendor submits."><button class="btn btn-primary">Invite vendor</button></EmptyState></div>

        <Modal :open="modal" title="Glass-strong modal" @close="modal = false">
            <p class="text-sm text-ink-muted">Dimmed, blurred backdrop (6 px, 40% scrim). Escape closes; focus is trapped.</p>
            <div class="mt-6 flex justify-end gap-2"><button class="btn btn-ghost" @click="modal = false">Cancel</button><button class="btn btn-primary" @click="modal = false">Confirm</button></div>
        </Modal>
    </AppLayout>
</template>
