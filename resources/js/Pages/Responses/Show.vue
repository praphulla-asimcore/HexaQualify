<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ResultBadge from '../../Components/ResultBadge.vue';
import ScoreBar from '../../Components/ScoreBar.vue';
import Icon from '../../Components/Icon.vue';
import Modal from '../../Components/Modal.vue';
import { api, formatDate, formatPoints, RESULT_LABELS } from '../../lib/format';
import { toast } from '../../lib/toast';

const props = defineProps({
    response: Object, invitation: Object, questionnaire: Object, score: Object, guide: Object,
    breakdown: Object, final_result: String, decisions: Array, audit: Array, can: Object,
});

const tab = ref('review');
const questions = computed(() => props.questionnaire.structure.sections.flatMap((s) => s.questions));
const qById = computed(() => Object.fromEntries(questions.value.map((q) => [q.id, q])));
const manual = reactive(Object.fromEntries(Object.entries(props.score?.manual ?? {}).map(([k, v]) => [k, v])));
const aiReasons = reactive(Object.fromEntries((props.score?.ai_suggestions ?? []).map((s) => [s.id, s])));
const manualDirty = ref(false);
const savingScores = ref(false);
const aiLoading = ref(false);
const manualErrors = ref({});

const scorable = (q) => q.type === 'text' && (props.guide?.[q.id]?.weight ?? 0) > 0;
const hasScorable = computed(() => questions.value.some(scorable));

function ruleText(q) {
    const g = props.guide?.[q.id];
    if (!g) return '';
    if (q.type === 'yesno') return `Expected ${g.expected === 'yes' ? 'Yes' : 'No'}`;
    if (q.type === 'number') return `${g.op === '<=' ? '≤' : '≥'} ${g.value}${q.unit ? ' ' + q.unit : ''}`;
    if (q.type === 'choice') return Object.entries(g.points || {}).map(([o, p]) => `${o} = ${formatPoints(p)}${(g.koOptions || []).includes(o) ? ' (KO)' : ''}`).join(' · ');
    return g.weight > 0 ? `Reviewer-scored, 0–${formatPoints(g.weight)} points` : 'Information only';
}
const isKoRule = (q) => !!(props.guide?.[q.id]?.ko || props.guide?.[q.id]?.koOptions?.length);
function answerText(q) {
    const a = props.response.answers[q.id];
    if (a === undefined || a === null || a === '') return '—';
    if (q.type === 'yesno') return a === 'yes' ? 'Yes' : 'No';
    if (q.type === 'number') return `${a}${q.unit ? ' ' + q.unit : ''}`;
    return a;
}

async function suggest() {
    aiLoading.value = true;
    try {
        const { suggestions } = await api(`/responses/${props.response.id}/ai-suggest`, { method: 'POST' });
        suggestions.forEach((s) => { manual[s.id] = s.points; aiReasons[s.id] = s; });
        manualDirty.value = suggestions.length > 0;
        toast(suggestions.length ? 'AI suggestions filled in. Review them, then click Save scores.' : 'No written answers to score.');
    } catch (e) {
        toast(e.message || 'AI suggestions are unavailable. Please score manually.', 'error');
    } finally {
        aiLoading.value = false;
    }
}
function saveScores() {
    savingScores.value = true;
    router.post(`/responses/${props.response.id}/manual-scores`, { manual: { ...manual } }, {
        preserveScroll: true,
        onSuccess: () => { manualDirty.value = false; manualErrors.value = {}; },
        onError: (e) => { manualErrors.value = e; },
        onFinish: () => { savingScores.value = false; },
    });
}

// Decision panel
const decision = useForm({ override: false, final_result: null, reason: '', notes: '', no_conflict: false });
const canUseCalculated = computed(() => ['Pass', 'Fail'].includes(props.score?.result));
function decide() {
    decision.transform((d) => ({ ...d, final_result: d.override ? d.final_result : null }))
        .post(`/responses/${props.response.id}/decision`, { preserveScroll: true, onSuccess: () => decision.reset() });
}

const rescoreOpen = ref(false);
function rescore() {
    router.post(`/responses/${props.response.id}/rescore`, {}, { preserveScroll: true, onFinish: () => (rescoreOpen.value = false) });
}

const vendorLabels = { company: 'Company', pan: 'PAN/VAT', contact_person: 'Contact person', designation: 'Designation', email: 'Email', phone: 'Phone' };
const actionLabels = {
    'invitation.create': 'Invitation created', 'invitation.resend': 'Invitation resent', 'invitation.opened': 'Link opened',
    'invitation.extend': 'Expiry extended', 'invitation.revoke': 'Invitation revoked', 'invitation.reminder': 'Reminder sent',
    'invitation.expire': 'Invitation expired', 'otp.sent': 'Verification code sent', 'otp.locked': 'Verification locked',
    'vendor.login': 'Vendor verified email', 'response.submit': 'Vendor submitted', 'response.view': 'Response viewed',
    'score.change': 'Score calculated', 'score.manual': 'Written answers scored', 'score.ai_suggest': 'AI suggestions requested',
    'score.rescore': 'Rescored with latest guide', decision: 'Decision recorded', 'decision.override': 'Decision overridden',
};
const auditEntries = computed(() => props.audit.filter((a) => a.action !== 'response.view' || showViews.value));
const showViews = ref(false);
function auditDetail(a) {
    const x = a.after || {};
    if (a.action.startsWith('decision')) return `${RESULT_LABELS[x.final_result] ?? x.final_result}${x.reason ? ` — “${x.reason}”` : ''}`;
    if (a.action === 'score.change' || a.action === 'score.rescore') return `${x.pct}% · ${RESULT_LABELS[x.result] ?? x.result}${x.guide_version ? ` · guide v${x.guide_version}` : ''}`;
    if (a.action === 'invitation.extend') return `New expiry ${formatDate(x.expires_at)}`;
    return '';
}
</script>

<template>
    <AppLayout :title="invitation.vendor_company">
        <Link href="/" class="btn btn-ghost btn-sm -ml-2 mb-3"><Icon name="arrowLeft" :size="14" />Dashboard</Link>

        <!-- Verdict header -->
        <section class="glass rise p-5 sm:p-7">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold sm:text-[28px]">{{ invitation.vendor_company }}</h1>
                    <p class="mt-1 text-ink-muted">
                        {{ questionnaire.name }} · v{{ questionnaire.version }}
                        <span v-if="score?.guide_version && score.guide_version !== questionnaire.version"> · scored with guide v{{ score.guide_version }}</span>
                        · received {{ formatDate(response.submitted_at, true) }}
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2 lg:items-end">
                    <ResultBadge :result="final_result" large />
                    <span v-if="decisions[0]?.override" class="text-xs text-ink-muted">Override of calculated result: {{ RESULT_LABELS[score?.result] }}</span>
                    <span v-else-if="!decisions.length" class="text-xs text-ink-muted">Calculated result · no decision recorded yet</span>
                </div>
            </div>

            <div v-if="score" class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr_1fr]">
                <div>
                    <ScoreBar :pct="score.pct" :pass-mark="score.pass_mark" />
                    <p class="mt-2 text-sm text-ink-muted">
                        <span class="font-mono font-semibold text-ink">{{ formatPoints(score.score) }}</span> of
                        <span class="font-mono">{{ formatPoints(score.max) }}</span> points
                    </p>
                </div>
                <div class="panel p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold" :class="score.knockouts.length ? 'text-notq' : 'text-qualified'">
                        <Icon :name="score.knockouts.length ? 'alert' : 'check'" :size="16" />
                        {{ score.knockouts.length ? `${score.knockouts.length} knockout${score.knockouts.length > 1 ? 's' : ''} failed` : 'No knockouts failed' }}
                    </div>
                    <ul v-if="score.knockouts.length" class="mt-2 space-y-1 text-sm">
                        <li v-for="id in score.knockouts" :key="id"><a :href="`#q-${id}`" class="hover:underline"><span class="font-mono text-ink-muted">{{ id }}</span> {{ qById[id]?.text }}</a></li>
                    </ul>
                </div>
                <div class="panel p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold" :class="score.pending.length ? 'text-review' : 'text-qualified'">
                        <Icon :name="score.pending.length ? 'clock' : 'check'" :size="16" />
                        {{ score.pending.length ? `${score.pending.length} written answer${score.pending.length > 1 ? 's' : ''} to score` : 'All answers scored' }}
                    </div>
                    <ul v-if="score.pending.length" class="mt-2 space-y-1 text-sm">
                        <li v-for="id in score.pending" :key="id"><a :href="`#q-${id}`" class="hover:underline"><span class="font-mono text-ink-muted">{{ id }}</span> {{ qById[id]?.text }}</a></li>
                    </ul>
                </div>
            </div>
            <p v-else class="mt-4 text-ink-muted">This response has not been scored.</p>
        </section>

        <div class="mt-5 flex gap-1" role="tablist">
            <button role="tab" class="btn btn-ghost" :aria-selected="tab === 'review'" :class="tab === 'review' ? '!bg-[var(--brand-tint-strong)]' : ''" @click="tab = 'review'">Review</button>
            <button v-if="can.review" role="tab" class="btn btn-ghost" :aria-selected="tab === 'audit'" :class="tab === 'audit' ? '!bg-[var(--brand-tint-strong)]' : ''" @click="tab = 'audit'"><Icon name="history" :size="16" />Audit trail</button>
        </div>

        <div v-show="tab === 'review'" class="mt-3 grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
            <!-- Per-question breakdown -->
            <div class="space-y-4">
                <div v-if="can.review && hasScorable" class="glass flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <span class="text-sm text-ink-muted">Score written answers 0 to max in steps of 0.5. Nothing is saved until you click Save.</span>
                    <div class="flex gap-2">
                        <button v-if="can.ai" type="button" class="btn btn-secondary btn-sm" :disabled="aiLoading" @click="suggest">
                            <Icon name="sparkles" :size="14" />{{ aiLoading ? 'Asking AI…' : 'Suggest scores with AI' }}
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" :disabled="savingScores || !manualDirty" @click="saveScores">{{ savingScores ? 'Saving…' : 'Save scores' }}</button>
                    </div>
                </div>

                <section v-for="section in questionnaire.structure.sections" :key="section.title" class="glass glass-strong p-5 sm:p-6">
                    <h2 class="text-lg font-semibold">{{ section.title }}</h2>
                    <div class="mt-2 divide-y divide-[var(--line)]">
                        <article v-for="q in section.questions" :key="q.id" :id="`q-${q.id}`" class="scroll-mt-28 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-medium leading-snug"><span class="mr-1.5 font-mono text-xs text-ink-muted">{{ q.id }}</span>{{ q.text }}</p>
                                    <p class="mt-2 whitespace-pre-line rounded-[var(--radius-sm)] bg-[var(--brand-tint)] px-3 py-2 text-[15px]">{{ answerText(q) }}</p>
                                </div>
                                <div v-if="breakdown?.[q.id]" class="shrink-0 text-right">
                                    <div class="font-mono text-sm"><span class="font-semibold">{{ formatPoints(breakdown[q.id].points) }}</span>/{{ formatPoints(breakdown[q.id].max) }}</div>
                                    <span v-if="breakdown[q.id].knockout" class="badge badge-fail mt-1">Knockout</span>
                                    <span v-else-if="breakdown[q.id].status === 'pending'" class="badge badge-review mt-1">To score</span>
                                </div>
                            </div>
                            <div v-if="guide?.[q.id]" class="mt-2 text-xs text-ink-muted">
                                <span class="font-semibold text-ink">Guide:</span> {{ ruleText(q) }}
                                <span v-if="isKoRule(q)" class="ml-1 font-semibold text-notq">· must-pass</span>
                                <span v-if="guide[q.id].note" class="block italic">“{{ guide[q.id].note }}”</span>
                            </div>
                            <div v-if="can.review && scorable(q)" class="mt-3 flex flex-wrap items-start gap-3">
                                <div>
                                    <label :for="`m-${q.id}`" class="label !text-xs">Points (0–{{ formatPoints(guide[q.id].weight) }})</label>
                                    <input :id="`m-${q.id}`" v-model.number="manual[q.id]" type="number" min="0" :max="guide[q.id].weight" step="0.5" class="field w-28 font-mono"
                                           :aria-invalid="!!manualErrors[`manual.${q.id}`]" @input="manualDirty = true" />
                                    <p v-if="manualErrors[`manual.${q.id}`]" class="error">{{ manualErrors[`manual.${q.id}`] }}</p>
                                </div>
                                <p v-if="aiReasons[q.id]" class="mt-6 flex max-w-md gap-1.5 text-xs text-ink-muted">
                                    <Icon name="sparkles" :size="14" class="shrink-0 text-brand-accent" />
                                    <span>AI suggested {{ formatPoints(aiReasons[q.id].points) }}: {{ aiReasons[q.id].reason }}</span>
                                </p>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <!-- Side panel -->
            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <section v-if="can.review" class="glass glass-strong p-5">
                    <h2 class="font-semibold">Decision</h2>
                    <form class="mt-3 space-y-3" @submit.prevent="decide">
                        <fieldset class="space-y-2">
                            <legend class="sr-only">Result</legend>
                            <label class="flex cursor-pointer items-start gap-2 text-sm" :class="{ 'opacity-60': !canUseCalculated }">
                                <input v-model="decision.override" type="radio" :value="false" class="mt-1 accent-[var(--hexa-accent)]" :disabled="!canUseCalculated" />
                                <span>Use calculated result <ResultBadge :result="score?.result" class="ml-1" />
                                    <span v-if="!canUseCalculated" class="block text-xs text-ink-muted">Score the written answers first.</span></span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input v-model="decision.override" type="radio" :value="true" class="accent-[var(--hexa-accent)]" />
                                Override
                            </label>
                        </fieldset>
                        <div v-if="decision.override" class="space-y-3 rounded-[var(--radius-sm)] bg-[var(--brand-tint)] p-3">
                            <div class="flex gap-2">
                                <label class="chip flex-1 justify-center !min-h-[40px]" :data-selected="decision.final_result === 'Pass'">
                                    <input v-model="decision.final_result" type="radio" value="Pass" class="sr-only" />Qualified
                                </label>
                                <label class="chip flex-1 justify-center !min-h-[40px]" :data-selected="decision.final_result === 'Fail'">
                                    <input v-model="decision.final_result" type="radio" value="Fail" class="sr-only" />Not qualified
                                </label>
                            </div>
                            <p v-if="decision.errors.final_result" class="error">{{ decision.errors.final_result }}</p>
                            <div>
                                <label for="reason" class="label">Reason <span class="text-notq">*</span></label>
                                <textarea id="reason" v-model="decision.reason" rows="3" class="field" required :aria-invalid="!!decision.errors.reason"></textarea>
                                <p v-if="decision.errors.reason" class="error">{{ decision.errors.reason }}</p>
                            </div>
                        </div>
                        <p v-if="decision.errors.override" class="error">{{ decision.errors.override }}</p>
                        <div>
                            <label for="notes" class="label">Reviewer notes</label>
                            <textarea id="notes" v-model="decision.notes" rows="2" class="field"></textarea>
                        </div>
                        <label class="flex cursor-pointer items-start gap-2 text-sm">
                            <input v-model="decision.no_conflict" type="checkbox" class="mt-1 h-4 w-4 accent-[var(--hexa-accent)]" />
                            <span>I confirm I have no personal or business relationship with this vendor.</span>
                        </label>
                        <p v-if="decision.errors.no_conflict" class="error">{{ decision.errors.no_conflict }}</p>
                        <button type="submit" class="btn btn-primary w-full" :disabled="decision.processing">Record decision</button>
                        <p v-if="invitation.notify_result" class="hint">The vendor will be emailed the outcome (never the score).</p>
                    </form>
                    <button v-if="can.rescore && questionnaire.latest_version !== score?.guide_version" type="button" class="btn btn-ghost btn-sm mt-3 w-full" @click="rescoreOpen = true">
                        <Icon name="refresh" :size="14" />Rescore with latest guide (v{{ questionnaire.latest_version }})
                    </button>
                </section>

                <section class="glass glass-strong p-5">
                    <h2 class="font-semibold">Vendor details</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div v-for="(label, key) in vendorLabels" :key="key" class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ label }}</dt>
                            <dd class="text-right font-medium" :class="{ 'font-mono': key === 'pan' }">{{ response.vendor[key] || '—' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 space-y-1 border-t border-[var(--line)] pt-3 text-xs text-ink-muted">
                        <div>Invited by {{ invitation.invited_by ?? '—' }} · {{ invitation.contact_email }}</div>
                        <div>Declaration {{ formatDate(response.declaration_at, true) }}<template v-if="response.submitted_ip"> · IP <span class="font-mono">{{ response.submitted_ip }}</span></template></div>
                        <div v-if="invitation.note" class="mt-2 rounded-[var(--radius-sm)] bg-[var(--brand-tint)] p-2 text-ink"><strong>Internal note:</strong> {{ invitation.note }}</div>
                    </div>
                </section>

                <section v-if="decisions.length" class="glass glass-strong p-5">
                    <h2 class="font-semibold">Decision history</h2>
                    <ol class="mt-3 space-y-3">
                        <li v-for="d in decisions" :key="d.id" class="text-sm">
                            <div class="flex items-center gap-2"><ResultBadge :result="d.final_result" :override="d.override" /></div>
                            <div class="mt-1 text-xs text-ink-muted">{{ d.decided_by }} · {{ formatDate(d.decided_at, true) }}</div>
                            <p v-if="d.reason" class="mt-1">“{{ d.reason }}”</p>
                            <p v-if="d.notes" class="mt-1 text-ink-muted">{{ d.notes }}</p>
                        </li>
                    </ol>
                </section>
            </aside>
        </div>

        <section v-if="tab === 'audit'" class="glass glass-strong mt-3 p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Audit trail</h2>
                <label class="flex items-center gap-2 text-sm text-ink-muted"><input v-model="showViews" type="checkbox" class="accent-[var(--hexa-accent)]" />Show views</label>
            </div>
            <ol class="mt-4 space-y-0">
                <li v-for="a in auditEntries" :key="a.id" class="flex gap-4 border-l-2 border-[var(--line)] pb-4 pl-4">
                    <div class="min-w-0">
                        <div class="text-sm font-medium">{{ actionLabels[a.action] ?? a.action }}</div>
                        <div class="text-xs text-ink-muted">{{ a.actor }} · {{ formatDate(a.at, true) }}</div>
                        <div v-if="auditDetail(a)" class="mt-1 text-sm">{{ auditDetail(a) }}</div>
                    </div>
                </li>
            </ol>
        </section>

        <Modal :open="rescoreOpen" title="Rescore with latest guide?" @close="rescoreOpen = false">
            <p class="text-sm text-ink-muted">This response was scored with guide v{{ score?.guide_version }}. Rescoring applies the current guide (v{{ questionnaire.latest_version }}) to the vendor's original answers. The previous score is kept in history and the change is logged.</p>
            <div class="mt-6 flex justify-end gap-2">
                <button class="btn btn-ghost" @click="rescoreOpen = false">Cancel</button>
                <button class="btn btn-primary" @click="rescore">Rescore</button>
            </div>
        </Modal>
    </AppLayout>
</template>
