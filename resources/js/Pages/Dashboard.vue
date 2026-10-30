<script setup>
import { computed, reactive, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import KpiTile from '../Components/KpiTile.vue';
import ResultBadge from '../Components/ResultBadge.vue';
import ScoreBar from '../Components/ScoreBar.vue';
import EmptyState from '../Components/EmptyState.vue';
import Icon from '../Components/Icon.vue';
import { formatDate, formatPct } from '../lib/format';

const props = defineProps({ kpis: Object, filters: Object, rows: Object, questionnaires: Array });
const f = reactive({ ...props.filters });
const canInvite = computed(() => ['admin', 'reviewer'].includes(usePage().props.auth.user.role));

let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get('/', clean(), { preserveState: true, preserveScroll: true, replace: true }), 300);
});
function clean() {
    return Object.fromEntries(Object.entries(f).filter(([, v]) => v !== null && v !== ''));
}
const exportUrl = computed(() => '/export/responses.csv?' + new URLSearchParams(clean()).toString());
const hasFilters = computed(() => Object.values(clean()).length > 0);
function reset() { Object.keys(f).forEach((k) => (f[k] = null)); }
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold sm:text-[28px]">Dashboard</h1>
                <p class="text-ink-muted">Pre-pitch vendor screening results.</p>
            </div>
            <Link v-if="canInvite" href="/invitations?new=1" class="btn btn-primary"><Icon name="plus" :size="16" />Invite vendor</Link>
        </div>

        <section class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5" aria-label="Key figures">
            <KpiTile label="Total responses" :value="kpis.total" />
            <KpiTile label="Qualified" :value="kpis.qualified" tone="pass" />
            <KpiTile label="Not qualified" :value="kpis.not_qualified" tone="fail" />
            <KpiTile label="Needs review" :value="kpis.review" tone="review" />
            <KpiTile label="Invitations pending" :value="kpis.pending_invitations" class="col-span-2 lg:col-span-1" />
        </section>

        <section class="glass mt-6 p-4" aria-label="Filters">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.4fr_1.2fr_1fr_1fr_1fr_auto]">
                <div class="relative">
                    <label for="q" class="sr-only">Search vendor</label>
                    <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
                    <input id="q" v-model="f.q" type="search" class="field !pl-9" placeholder="Search vendor or email" />
                </div>
                <div>
                    <label for="fq" class="sr-only">Questionnaire</label>
                    <select id="fq" v-model="f.questionnaire" class="field">
                        <option :value="null">All questionnaires</option>
                        <option v-for="q in questionnaires" :key="q.id" :value="q.id">{{ q.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="fr" class="sr-only">Result</label>
                    <select id="fr" v-model="f.result" class="field">
                        <option :value="null">All results</option>
                        <option value="Pass">Qualified</option>
                        <option value="Fail">Not qualified</option>
                        <option value="Review">Needs review</option>
                    </select>
                </div>
                <div><label for="ff" class="sr-only">From</label><input id="ff" v-model="f.from" type="date" class="field" aria-label="Received from" /></div>
                <div><label for="ft" class="sr-only">To</label><input id="ft" v-model="f.to" type="date" class="field" aria-label="Received to" /></div>
                <div class="flex gap-2">
                    <button v-if="hasFilters" type="button" class="btn btn-ghost" @click="reset">Clear</button>
                    <a :href="exportUrl" class="btn btn-secondary" download><Icon name="download" :size="16" />Export CSV</a>
                </div>
            </div>
        </section>

        <section class="mt-4">
            <EmptyState v-if="!rows.data.length" :title="hasFilters ? 'No matching responses' : 'No responses yet'"
                        :text="hasFilters ? 'Try a different filter.' : 'Responses appear here as soon as a vendor submits their questionnaire.'">
                <button v-if="hasFilters" class="btn btn-secondary" @click="reset">Clear filters</button>
                <Link v-else-if="canInvite" href="/invitations?new=1" class="btn btn-primary"><Icon name="plus" :size="16" />Invite a vendor</Link>
            </EmptyState>

            <div v-else class="glass glass-strong overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table-glass min-w-[860px]">
                        <thead>
                            <tr>
                                <th scope="col">Vendor</th>
                                <th scope="col">Questionnaire</th>
                                <th scope="col">Received</th>
                                <th scope="col" class="w-[200px]">Score</th>
                                <th scope="col" class="text-center">Knockouts</th>
                                <th scope="col">Final result</th>
                                <th scope="col">Decided by</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in rows.data" :key="r.id" class="cursor-pointer" @click="router.visit(`/responses/${r.id}`)">
                                <td>
                                    <Link :href="`/responses/${r.id}`" class="font-semibold hover:underline" @click.stop>{{ r.vendor }}</Link>
                                    <div class="text-xs text-ink-muted">{{ r.contact_email }}</div>
                                </td>
                                <td><div>{{ r.questionnaire }}</div><div class="text-xs text-ink-muted">{{ r.category }}</div></td>
                                <td class="whitespace-nowrap">{{ formatDate(r.received) }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="w-14 font-mono text-sm">{{ formatPct(r.pct) }}</span>
                                        <ScoreBar :pct="r.pct" :pass-mark="r.pass_mark" compact class="flex-1" />
                                    </div>
                                </td>
                                <td class="text-center font-mono" :class="r.knockouts ? 'font-semibold text-notq' : 'text-ink-muted'">{{ r.knockouts }}</td>
                                <td><ResultBadge :result="r.final_result" :override="r.override" /></td>
                                <td class="text-sm">{{ r.decided_by ?? '—' }}<div v-if="r.decided_at" class="text-xs text-ink-muted">{{ formatDate(r.decided_at) }}</div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="rows.last_page > 1" class="flex items-center justify-between border-t border-[var(--line)] px-4 py-3 text-sm">
                    <span class="text-ink-muted">{{ rows.from }}–{{ rows.to }} of {{ rows.total }}</span>
                    <div class="flex gap-2">
                        <Link v-if="rows.prev_page_url" :href="rows.prev_page_url" preserve-scroll class="btn btn-sm btn-secondary">Previous</Link>
                        <Link v-if="rows.next_page_url" :href="rows.next_page_url" preserve-scroll class="btn btn-sm btn-secondary">Next</Link>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
