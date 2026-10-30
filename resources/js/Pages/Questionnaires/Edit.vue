<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Icon from '../../Components/Icon.vue';
import Modal from '../../Components/Modal.vue';
import { formatDate, formatPoints } from '../../lib/format';

const props = defineProps({ questionnaire: Object, editor: Object, versions: Array, blocks: Array });

const TYPES = [
    { value: 'yesno', label: 'Yes / No' },
    { value: 'choice', label: 'Single choice' },
    { value: 'number', label: 'Number' },
    { value: 'text', label: 'Written answer' },
];
const clone = (x) => JSON.parse(JSON.stringify(x));
const model = reactive(clone(props.editor));
const errors = ref({});
const saving = ref(false);
const historyOpen = ref(false);
const archiveOpen = ref(false);
const viewingOld = computed(() => props.questionnaire && props.questionnaire.version !== props.questionnaire.latest);

function blankQuestion(type) {
    const ids = model.sections.flatMap((s) => s.questions.map((q) => q.id));
    let n = ids.length + 1, id = `q${n}`;
    while (ids.includes(id)) id = `q${++n}`;
    const q = { id, type, text: '', help: '', required: true, guide: { weight: 5, note: '' } };
    if (type === 'yesno') Object.assign(q.guide, { expected: 'yes', ko: false });
    if (type === 'choice') { q.options = ['', '']; Object.assign(q.guide, { points: {}, koOptions: [] }); }
    if (type === 'number') { q.unit = ''; Object.assign(q.guide, { op: '>=', value: null, ko: false }); }
    return q;
}
function addSection() { model.sections.push({ title: '', block: null, questions: [blankQuestion('yesno')] }); }
function addBlock(b) { model.sections.unshift(clone(b)); }
const unusedBlocks = computed(() => props.blocks.filter((b) => !model.sections.some((s) => s.block === b.block)));
function move(list, i, d) { const j = i + d; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; }
function remove(list, i) { list.splice(i, 1); }

function setType(q, type) {
    const fresh = blankQuestion(type);
    q.type = type;
    q.guide = { ...fresh.guide, weight: q.guide.weight ?? 5, note: q.guide.note ?? '' };
    if (type === 'choice') q.options = q.options?.length ? q.options : ['', '']; else delete q.options;
    if (type === 'number') q.unit = q.unit ?? ''; else delete q.unit;
}
function optionPoints(q, o) { return q.guide.points?.[o] ?? 0; }
function setOptionPoints(q, o, v) { q.guide.points = { ...(q.guide.points || {}), [o]: Number(v) }; }
function toggleKo(q, o) {
    const ko = new Set(q.guide.koOptions || []);
    ko.has(o) ? ko.delete(o) : ko.add(o);
    q.guide.koOptions = [...ko];
}
function renameOption(q, i, v) {
    const old = q.options[i];
    q.options[i] = v;
    if (q.guide.points && old in q.guide.points) { q.guide.points[v] = q.guide.points[old]; delete q.guide.points[old]; }
    q.guide.koOptions = (q.guide.koOptions || []).map((k) => (k === old ? v : k));
}

const maxFor = (q) => (q.type === 'choice' ? Math.max(0, ...q.options.map((o) => Number(q.guide.points?.[o] ?? 0))) : Number(q.guide.weight || 0));
const totals = computed(() => {
    const qs = model.sections.flatMap((s) => s.questions);
    return {
        questions: qs.length,
        points: qs.reduce((a, q) => a + maxFor(q), 0),
        knockouts: qs.filter((q) => q.guide.ko || q.guide.koOptions?.length).length,
        written: qs.filter((q) => q.type === 'text' && Number(q.guide.weight) > 0).length,
    };
});
const err = (path) => errors.value[path];

function save() {
    saving.value = true;
    const payload = clone(model);
    payload.sections.forEach((s) => s.questions.forEach((q) => { if (q.type !== 'choice') return; q.options = q.options.filter((o) => o.trim()); }));
    const opts = {
        preserveScroll: true,
        onError: (e) => {
            errors.value = e;
            setTimeout(() => document.querySelector('[aria-invalid="true"], .error')?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 50);
        },
        onSuccess: () => (errors.value = {}),
        onFinish: () => (saving.value = false),
    };
    props.questionnaire ? router.put(`/questionnaires/${props.questionnaire.id}`, payload, opts) : router.post('/questionnaires', payload, opts);
}
</script>

<template>
    <AppLayout :title="questionnaire ? `Edit ${editor.name}` : 'New questionnaire'">
        <Link href="/questionnaires" class="btn btn-ghost btn-sm -ml-2 mb-3"><Icon name="arrowLeft" :size="14" />Questionnaires</Link>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold sm:text-[28px]">{{ questionnaire ? editor.name : 'New questionnaire' }}</h1>
                <p v-if="questionnaire" class="text-ink-muted">Version {{ questionnaire.version }} of {{ questionnaire.latest }} · every save creates a new immutable version</p>
            </div>
            <div v-if="questionnaire" class="flex flex-wrap gap-2">
                <button class="btn btn-ghost btn-sm" @click="historyOpen = true"><Icon name="history" :size="14" />Version history</button>
                <a :href="`/questionnaires/${questionnaire.id}/preview`" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><Icon name="eye" :size="14" />Preview as vendor</a>
                <button class="btn btn-ghost btn-sm" @click="router.post(`/questionnaires/${questionnaire.id}/duplicate`)"><Icon name="duplicate" :size="14" />Duplicate</button>
                <button v-if="!questionnaire.archived" class="btn btn-danger btn-sm" @click="archiveOpen = true"><Icon name="archive" :size="14" />Archive</button>
            </div>
        </div>

        <div v-if="viewingOld" class="glass mt-4 flex items-center gap-3 px-5 py-3 text-sm" role="note">
            <Icon name="history" class="shrink-0 text-review" />
            <span>You are viewing version {{ questionnaire.version }}. Saving will publish it as a new version {{ questionnaire.latest + 1 }}.</span>
            <Link :href="`/questionnaires/${questionnaire.id}/edit`" class="btn btn-secondary btn-sm ml-auto">Open latest</Link>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
            <div class="space-y-4">
                <section class="glass glass-strong p-5 sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="qn" class="label">Name <span class="text-notq">*</span></label>
                            <input id="qn" v-model="model.name" class="field" :aria-invalid="!!err('name')" />
                            <p v-if="err('name')" class="error">{{ err('name') }}</p>
                        </div>
                        <div>
                            <label for="qc" class="label">Service category <span class="text-notq">*</span></label>
                            <input id="qc" v-model="model.category" class="field" :aria-invalid="!!err('category')" />
                            <p v-if="err('category')" class="error">{{ err('category') }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="qd" class="label">Description shown to vendors</label>
                            <textarea id="qd" v-model="model.description" rows="2" class="field"></textarea>
                        </div>
                        <div>
                            <label for="qp" class="label">Pass mark (% of max points) <span class="text-notq">*</span></label>
                            <input id="qp" v-model.number="model.pass_mark" type="number" min="0" max="100" step="0.5" class="field w-32 font-mono" :aria-invalid="!!err('pass_mark')" />
                            <p v-if="err('pass_mark')" class="error">{{ err('pass_mark') }}</p>
                        </div>
                        <label class="flex items-center gap-2 self-end pb-3 text-sm">
                            <input v-model="model.active" type="checkbox" class="h-4 w-4 accent-[var(--hexa-accent)]" />Active (available for new invitations)
                        </label>
                    </div>
                </section>

                <section v-for="(s, si) in model.sections" :key="si" class="glass glass-strong p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs text-ink-muted">§{{ si + 1 }}</span>
                        <input v-model="s.title" class="field max-w-md flex-1 font-semibold" placeholder="Section title" :disabled="!!s.block" :aria-label="`Section ${si + 1} title`" :aria-invalid="!!err(`sections.${si}.title`)" />
                        <span v-if="s.block" class="badge badge-neutral"><Icon name="lock" :size="12" />Shared baseline</span>
                        <div class="ml-auto flex gap-1">
                            <button class="btn btn-ghost btn-sm" @click="move(model.sections, si, -1)" :disabled="si === 0" aria-label="Move section up"><Icon name="chevronUp" :size="14" /></button>
                            <button class="btn btn-ghost btn-sm" @click="move(model.sections, si, 1)" :disabled="si === model.sections.length - 1" aria-label="Move section down"><Icon name="chevronDown" :size="14" /></button>
                            <button class="btn btn-ghost btn-sm text-notq" @click="remove(model.sections, si)" aria-label="Remove section"><Icon name="trash" :size="14" /></button>
                        </div>
                    </div>
                    <p v-if="err(`sections.${si}.title`)" class="error">{{ err(`sections.${si}.title`) }}</p>
                    <p v-if="err(`sections.${si}.questions`)" class="error">{{ err(`sections.${si}.questions`) }}</p>
                    <p v-if="s.block" class="hint">Included by reference in every category. Its questions and guide are kept in sync with the shared block.</p>

                    <div class="mt-4 space-y-3">
                        <article v-for="(q, qi) in s.questions" :key="qi" class="panel p-4">
                            <fieldset :disabled="!!s.block" class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input v-model.trim="q.id" class="field !min-h-[36px] w-20 font-mono text-sm" aria-label="Question id" :aria-invalid="!!err(`sections.${si}.questions.${qi}.id`)" />
                                    <select :value="q.type" class="field !min-h-[36px] w-40 text-sm" aria-label="Question type" @change="setType(q, $event.target.value)">
                                        <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                    </select>
                                    <label class="flex items-center gap-1.5 text-sm"><input v-model="q.required" type="checkbox" class="accent-[var(--hexa-accent)]" />Required</label>
                                    <span v-if="q.guide.ko || q.guide.koOptions?.length" class="badge badge-fail">Knockout</span>
                                    <span class="ml-auto font-mono text-xs text-ink-muted">{{ formatPoints(maxFor(q)) }} pts</span>
                                    <template v-if="!s.block">
                                        <button type="button" class="btn btn-ghost btn-sm" @click="move(s.questions, qi, -1)" :disabled="qi === 0" aria-label="Move up"><Icon name="chevronUp" :size="14" /></button>
                                        <button type="button" class="btn btn-ghost btn-sm" @click="move(s.questions, qi, 1)" :disabled="qi === s.questions.length - 1" aria-label="Move down"><Icon name="chevronDown" :size="14" /></button>
                                        <button type="button" class="btn btn-ghost btn-sm text-notq" @click="remove(s.questions, qi)" aria-label="Delete question"><Icon name="trash" :size="14" /></button>
                                    </template>
                                </div>
                                <p v-if="err(`sections.${si}.questions.${qi}.id`)" class="error">{{ err(`sections.${si}.questions.${qi}.id`) }}</p>
                                <div>
                                    <textarea v-model="q.text" rows="2" class="field" placeholder="Question text" aria-label="Question text" :aria-invalid="!!err(`sections.${si}.questions.${qi}.text`)"></textarea>
                                    <p v-if="err(`sections.${si}.questions.${qi}.text`)" class="error">{{ err(`sections.${si}.questions.${qi}.text`) }}</p>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-[1fr_140px]">
                                    <input v-model="q.help" class="field text-sm" placeholder="Help text shown to vendors (optional)" aria-label="Help text" />
                                    <input v-if="q.type === 'number'" v-model="q.unit" class="field text-sm" placeholder="Unit, e.g. hours" aria-label="Unit" />
                                </div>

                                <div v-if="q.type === 'choice'" class="space-y-2">
                                    <div v-for="(o, oi) in q.options" :key="oi" class="flex items-center gap-2">
                                        <input :value="o" class="field !min-h-[36px] flex-1 text-sm" :placeholder="`Option ${oi + 1}`" aria-label="Option" @change="renameOption(q, oi, $event.target.value)" />
                                        <input :value="optionPoints(q, o)" type="number" min="0" step="0.5" class="field !min-h-[36px] w-20 font-mono text-sm" aria-label="Points" title="Points (hidden from vendors)" @input="setOptionPoints(q, o, $event.target.value)" />
                                        <label class="flex items-center gap-1 text-xs" title="Choosing this option is a knockout"><input type="checkbox" :checked="q.guide.koOptions?.includes(o)" class="accent-[var(--not-qualified)]" @change="toggleKo(q, o)" />KO</label>
                                        <button v-if="!s.block" type="button" class="btn btn-ghost btn-sm" @click="q.options.splice(oi, 1)" aria-label="Remove option"><Icon name="x" :size="14" /></button>
                                    </div>
                                    <button v-if="!s.block" type="button" class="btn btn-ghost btn-sm" @click="q.options.push('')"><Icon name="plus" :size="14" />Add option</button>
                                    <p v-if="err(`sections.${si}.questions.${qi}.options`)" class="error">{{ err(`sections.${si}.questions.${qi}.options`) }}</p>
                                </div>

                                <!-- Hidden scoring guide -->
                                <div class="guide rounded-[var(--radius-sm)] p-3">
                                    <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-ink-muted"><Icon name="lock" :size="12" />Scoring guide · hidden from vendors</div>
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div v-if="q.type !== 'choice'">
                                            <label class="label !text-xs">Points</label>
                                            <input v-model.number="q.guide.weight" type="number" min="0" step="0.5" class="field !min-h-[36px] w-20 font-mono text-sm" />
                                        </div>
                                        <div v-if="q.type === 'yesno'">
                                            <label class="label !text-xs">Expected answer</label>
                                            <select v-model="q.guide.expected" class="field !min-h-[36px] w-24 text-sm"><option value="yes">Yes</option><option value="no">No</option></select>
                                        </div>
                                        <template v-if="q.type === 'number'">
                                            <div>
                                                <label class="label !text-xs">Met when answer is</label>
                                                <select v-model="q.guide.op" class="field !min-h-[36px] w-20 text-sm"><option value=">=">≥</option><option value="<=">≤</option></select>
                                            </div>
                                            <div>
                                                <label class="label !text-xs">Threshold</label>
                                                <input v-model.number="q.guide.value" type="number" step="any" class="field !min-h-[36px] w-24 font-mono text-sm" :aria-invalid="!!err(`sections.${si}.questions.${qi}.guide.value`)" />
                                            </div>
                                        </template>
                                        <label v-if="q.type === 'yesno' || q.type === 'number'" class="flex items-center gap-1.5 pb-2 text-sm">
                                            <input v-model="q.guide.ko" type="checkbox" class="accent-[var(--not-qualified)]" />Knockout if not met
                                        </label>
                                        <span v-if="q.type === 'text'" class="pb-2 text-xs text-ink-muted">Reviewer-scored; AI can suggest. 0 points = information only.</span>
                                    </div>
                                    <p v-if="err(`sections.${si}.questions.${qi}.guide.value`)" class="error">{{ err(`sections.${si}.questions.${qi}.guide.value`) }}</p>
                                    <input v-model="q.guide.note" class="field mt-2 !min-h-[36px] text-sm" placeholder="What good looks like (for reviewers and AI)" aria-label="Guide note" />
                                </div>
                            </fieldset>
                        </article>
                    </div>
                    <div v-if="!s.block" class="mt-3 flex flex-wrap gap-2">
                        <button v-for="t in TYPES" :key="t.value" type="button" class="btn btn-ghost btn-sm" @click="s.questions.push(blankQuestion(t.value))"><Icon name="plus" :size="14" />{{ t.label }}</button>
                    </div>
                </section>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary" @click="addSection"><Icon name="plus" :size="16" />Add section</button>
                    <button v-for="b in unusedBlocks" :key="b.block" type="button" class="btn btn-ghost" @click="addBlock(b)"><Icon name="plus" :size="16" />Include “{{ b.title }}”</button>
                </div>
                <p v-if="err('sections')" class="error">{{ err('sections') }}</p>
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="glass glass-strong space-y-4 p-5">
                    <h2 class="font-semibold">Summary</h2>
                    <dl class="grid grid-cols-2 gap-3 text-center">
                        <div class="panel p-3"><dt class="text-xs text-ink-muted">Questions</dt><dd class="font-display text-xl font-semibold">{{ totals.questions }}</dd></div>
                        <div class="panel p-3"><dt class="text-xs text-ink-muted">Max points</dt><dd class="font-display text-xl font-semibold">{{ formatPoints(totals.points) }}</dd></div>
                        <div class="panel p-3"><dt class="text-xs text-ink-muted">Knockouts</dt><dd class="font-display text-xl font-semibold">{{ totals.knockouts }}</dd></div>
                        <div class="panel p-3"><dt class="text-xs text-ink-muted">Written</dt><dd class="font-display text-xl font-semibold">{{ totals.written }}</dd></div>
                    </dl>
                    <p class="text-xs text-ink-muted">Pass needs {{ formatPoints(Math.ceil(totals.points * model.pass_mark / 10) / 10) }} of {{ formatPoints(totals.points) }} points and no failed knockouts.</p>
                    <p v-if="Object.keys(errors).length" class="error">Fix the highlighted fields before publishing.</p>
                    <button type="button" class="btn btn-primary w-full" :disabled="saving" @click="save">{{ saving ? 'Publishing…' : questionnaire ? `Publish version ${questionnaire.latest + 1}` : 'Publish version 1' }}</button>
                    <p class="hint">Existing invitations and responses keep the version they were sent with.</p>
                </div>
            </aside>
        </div>

        <Modal :open="historyOpen" title="Version history" @close="historyOpen = false">
            <ol class="divide-y divide-[var(--line)]">
                <li v-for="v in versions" :key="v.version" class="flex items-center gap-3 py-3">
                    <span class="font-mono font-semibold">v{{ v.version }}</span>
                    <div class="flex-1 text-sm">
                        <div>{{ formatDate(v.created_at, true) }} · {{ v.created_by ?? 'Seed' }}</div>
                        <div class="text-xs text-ink-muted">{{ v.questions }} questions · pass mark {{ v.pass_mark }}% · {{ v.invitations }} invitation(s)</div>
                    </div>
                    <Link :href="`/questionnaires/${questionnaire.id}/edit?version=${v.version}`" class="btn btn-ghost btn-sm" @click="historyOpen = false">View</Link>
                </li>
            </ol>
        </Modal>

        <Modal :open="archiveOpen" title="Archive questionnaire?" @close="archiveOpen = false">
            <p class="text-sm text-ink-muted">It will no longer be available for new invitations. Existing responses and scores are kept. You can restore it later.</p>
            <div class="mt-6 flex justify-end gap-2">
                <button class="btn btn-ghost" @click="archiveOpen = false">Cancel</button>
                <button class="btn btn-danger" @click="router.post(`/questionnaires/${questionnaire.id}/archive`)">Archive</button>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.guide { background: var(--brand-tint); border: 1px dashed var(--brand-tint-strong); }
</style>
