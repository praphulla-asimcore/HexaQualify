<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import Icon from '../../Components/Icon.vue';
import { api, formatDate } from '../../lib/format';
import { toast } from '../../lib/toast';

const props = defineProps({
    token: String, preview: Boolean, questionnaire: Object, deadline: String,
    vendor: Object, answers: Object, savedAt: String,
});

const TEXT_MAX = 3000;
const vendorFields = [
    { key: 'company', label: 'Company name', required: true, autocomplete: 'organization' },
    { key: 'pan', label: 'PAN/VAT number', required: true, mono: true, hint: 'Foreign vendors: your tax registration number.' },
    { key: 'contact_person', label: 'Contact person', required: true, autocomplete: 'name' },
    { key: 'designation', label: 'Designation', autocomplete: 'organization-title' },
    { key: 'email', label: 'Email', required: true, type: 'email', autocomplete: 'email' },
    { key: 'phone', label: 'Phone', required: true, type: 'tel', autocomplete: 'tel' },
];

const vendor = reactive({ ...props.vendor });
const answers = reactive({ ...props.answers });
const declaration = ref(false);
const errors = ref({});
const saveState = ref(props.savedAt ? 'saved' : 'idle'); // idle | dirty | saving | saved | error
const savedAt = ref(props.savedAt);
const submitting = ref(false);
const stepperOpen = ref(false);

const sections = computed(() => [
    { title: 'Your company', key: 'vendor', questions: vendorFields.filter((f) => f.required).map((f) => ({ id: `vendor.${f.key}` })) },
    ...props.questionnaire.structure.sections.map((s, i) => ({ ...s, key: `s${i}` })),
]);

const isAnswered = (id) => {
    const v = id.startsWith('vendor.') ? vendor[id.slice(7)] : answers[id];
    return v !== undefined && v !== null && String(v).trim() !== '';
};
const progress = computed(() => sections.value.map((s) => {
    const req = s.questions.filter((q) => q.required !== false);
    return { key: s.key, title: s.title, done: req.filter((q) => isAnswered(q.id)).length, total: req.length };
}));
const overall = computed(() => {
    const t = progress.value.reduce((a, p) => a + p.total, 0);
    const d = progress.value.reduce((a, p) => a + p.done, 0);
    return t ? Math.round((d / t) * 100) : 0;
});

// ---- Autosave: every 10 s when changed, and shortly after a field loses focus.
let dirty = false;
let blurTimer = null;
watch([vendor, answers], () => {
    dirty = true;
    if (saveState.value !== 'saving') saveState.value = 'dirty';
}, { deep: true });

async function save() {
    if (props.preview || !dirty || submitting.value) return;
    dirty = false;
    saveState.value = 'saving';
    try {
        const res = await api(`/v/${props.token}/draft`, { method: 'PUT', body: { vendor, answers } });
        savedAt.value = res.savedAt;
        saveState.value = dirty ? 'dirty' : 'saved';
    } catch (e) {
        dirty = true;
        saveState.value = 'error';
        if (e.status === 410 || e.status === 403) router.reload();
    }
}
async function saveNow() {
    dirty = true;
    await save();
    if (saveState.value === 'saved') toast('Saved. You can close this page and continue later from the same link.');
}
function onBlur() {
    clearTimeout(blurTimer);
    blurTimer = setTimeout(save, 1500);
}
let interval;
onMounted(() => { interval = setInterval(save, 10000); window.addEventListener('beforeunload', warn); });
onBeforeUnmount(() => { clearInterval(interval); clearTimeout(blurTimer); window.removeEventListener('beforeunload', warn); });
function warn(e) { if (dirty && !props.preview) { save(); e.preventDefault(); } }

// ---- Validation mirrors the server so missing items are highlighted before submitting.
function validate() {
    const e = {};
    vendorFields.forEach((f) => {
        if (f.required && !isAnswered(`vendor.${f.key}`)) e[`vendor.${f.key}`] = `${f.label} is required.`;
    });
    if (vendor.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(vendor.email)) e['vendor.email'] = 'Enter a valid email address.';
    props.questionnaire.structure.sections.forEach((s) => s.questions.forEach((q) => {
        const a = answers[q.id];
        if (q.required !== false && !isAnswered(q.id)) e[`answers.${q.id}`] = 'This question is required.';
        else if (q.type === 'number' && isAnswered(q.id) && !/^-?\d+(\.\d+)?$/.test(String(a).trim())) e[`answers.${q.id}`] = 'Enter a number.';
        else if (q.type === 'text' && a && a.length > TEXT_MAX) e[`answers.${q.id}`] = `Keep this under ${TEXT_MAX} characters.`;
    }));
    if (!declaration.value) e.declaration = 'Please confirm the declaration.';
    return e;
}

async function scrollToFirstError() {
    await nextTick();
    const el = document.querySelector('[data-invalid="true"]');
    if (!el) return;
    el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
    el.querySelector('input, textarea')?.focus({ preventScroll: true });
}

function submit() {
    if (props.preview) return toast('Preview only — vendors submit from their own link.');
    errors.value = validate();
    if (Object.keys(errors.value).length) {
        toast(`Please complete ${Object.keys(errors.value).length} highlighted item(s).`, 'error');
        return scrollToFirstError();
    }
    submitting.value = true;
    clearInterval(interval);
    router.post(`/v/${props.token}/submit`, { vendor, answers, declaration: declaration.value }, {
        onError: (e) => { errors.value = e; toast('Some answers need attention.', 'error'); scrollToFirstError(); },
        onFinish: () => { submitting.value = false; },
    });
}

function setAnswer(id, value) {
    answers[id] = value;
    delete errors.value[`answers.${id}`];
    onBlur();
}
function jump(key) {
    stepperOpen.value = false;
    document.getElementById(`section-${key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
const saveLabel = computed(() => ({
    idle: 'Not saved yet', dirty: 'Unsaved changes', saving: 'Saving…',
    saved: savedAt.value ? `Saved ${formatDate(savedAt.value, true).split(', ').pop()}` : 'Saved', error: 'Could not save — retrying',
}[saveState.value]));
</script>

<template>
    <VendorLayout :title="questionnaire.name">
        <div v-if="preview" class="glass mb-4 flex items-center gap-3 px-5 py-3 text-sm" role="note">
            <Icon name="eye" class="shrink-0 text-brand-accent" /><span><strong>Preview.</strong> This is exactly what vendors see. Nothing is saved.</span>
        </div>

        <section class="glass glass-strong rise p-6 sm:p-8">
            <h1 class="text-2xl font-semibold sm:text-[28px]">{{ questionnaire.name }}</h1>
            <p v-if="questionnaire.description" class="mt-2 text-ink-muted">{{ questionnaire.description }}</p>
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                <span class="flex items-center gap-1.5 text-ink-muted"><Icon name="clock" :size="16" />Due {{ formatDate(deadline) }}</span>
                <span class="flex items-center gap-1.5 text-ink-muted"><Icon name="check" :size="16" />Answers save automatically</span>
            </div>
            <p class="mt-4 text-sm text-ink-muted">Answer every question marked <span class="text-notq">*</span>. Be specific in written answers; they are read by our procurement team.</p>
        </section>

        <!-- Section stepper: sticky on desktop, collapsible on mobile -->
        <div class="glass sticky top-2 z-30 mt-4 px-4 py-3 sm:top-4">
            <div class="flex items-center gap-3">
                <button type="button" class="flex flex-1 items-center gap-3 text-left sm:pointer-events-none" @click="stepperOpen = !stepperOpen" :aria-expanded="stepperOpen" aria-controls="stepper">
                    <span class="font-display text-sm font-semibold">{{ overall }}% complete</span>
                    <span class="hidden h-1.5 flex-1 overflow-hidden rounded-full bg-[var(--line)] sm:block"><span class="block h-full bg-[var(--hexa-gradient)]" :style="{ width: overall + '%' }"></span></span>
                    <Icon :name="stepperOpen ? 'chevronUp' : 'chevronDown'" :size="16" class="sm:hidden" />
                </button>
                <span class="text-xs text-ink-muted" aria-live="polite" :class="{ 'text-notq': saveState === 'error' }">{{ preview ? 'Preview' : saveLabel }}</span>
            </div>
            <ol id="stepper" class="mt-3 gap-1.5 sm:flex sm:flex-wrap" :class="stepperOpen ? 'grid' : 'hidden sm:flex'">
                <li v-for="(p, i) in progress" :key="p.key">
                    <button type="button" class="btn btn-sm btn-ghost w-full !justify-start sm:w-auto" @click="jump(p.key)">
                        <span class="grid h-5 w-5 place-items-center rounded-full text-[10px] font-bold"
                              :class="p.done === p.total ? 'bg-qualified-bg text-qualified' : 'bg-[var(--brand-tint-strong)] text-ink'">
                            <Icon v-if="p.done === p.total" name="check" :size="12" /><template v-else>{{ i + 1 }}</template>
                        </span>
                        {{ p.title }}<span class="font-mono text-ink-muted">{{ p.done }}/{{ p.total }}</span>
                    </button>
                </li>
            </ol>
        </div>

        <form class="mt-4 space-y-4" novalidate @submit.prevent="submit">
            <!-- Vendor details -->
            <section id="section-vendor" class="glass glass-strong scroll-mt-28 p-6 sm:p-8">
                <h2 class="text-lg font-semibold">Your company</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div v-for="f in vendorFields" :key="f.key" :data-invalid="!!errors[`vendor.${f.key}`]">
                        <label :for="`v-${f.key}`" class="label">{{ f.label }}<span v-if="f.required" class="text-notq" aria-hidden="true"> *</span></label>
                        <input :id="`v-${f.key}`" v-model.trim="vendor[f.key]" :type="f.type || 'text'" class="field" :class="{ 'font-mono': f.mono }"
                               :autocomplete="f.autocomplete" maxlength="200" :required="f.required" :aria-invalid="!!errors[`vendor.${f.key}`]"
                               @blur="onBlur" @input="delete errors[`vendor.${f.key}`]" />
                        <p v-if="errors[`vendor.${f.key}`]" class="error">{{ errors[`vendor.${f.key}`] }}</p>
                        <p v-else-if="f.hint" class="hint">{{ f.hint }}</p>
                    </div>
                </div>
            </section>

            <section v-for="(section, si) in questionnaire.structure.sections" :key="si" :id="`section-s${si}`" class="glass glass-strong scroll-mt-28 p-6 sm:p-8">
                <h2 class="text-lg font-semibold">{{ section.title }}</h2>
                <div class="mt-2 divide-y divide-[var(--line)]">
                    <fieldset v-for="q in section.questions" :key="q.id" :id="`q-${q.id}`" class="py-5" :data-invalid="!!errors[`answers.${q.id}`]">
                        <legend class="float-left w-full text-[15px] font-medium leading-snug">
                            {{ q.text }}<span v-if="q.required !== false" class="text-notq" aria-hidden="true"> *</span>
                        </legend>
                        <div class="clear-both"></div>
                        <p v-if="q.help" class="hint !mt-1">{{ q.help }}</p>

                        <div v-if="q.type === 'yesno'" class="mt-3 flex flex-wrap gap-2" role="radiogroup">
                            <label v-for="opt in [['yes', 'Yes'], ['no', 'No']]" :key="opt[0]" class="chip" :data-selected="answers[q.id] === opt[0]">
                                <input type="radio" class="sr-only" :name="q.id" :value="opt[0]" :checked="answers[q.id] === opt[0]" @change="setAnswer(q.id, opt[0])" />
                                <Icon v-if="answers[q.id] === opt[0]" name="check" :size="14" />{{ opt[1] }}
                            </label>
                        </div>

                        <div v-else-if="q.type === 'choice'" class="mt-3 flex flex-wrap gap-2" role="radiogroup">
                            <label v-for="opt in q.options" :key="opt" class="chip !rounded-[var(--radius-md)] text-left" :data-selected="answers[q.id] === opt">
                                <input type="radio" class="sr-only" :name="q.id" :value="opt" :checked="answers[q.id] === opt" @change="setAnswer(q.id, opt)" />
                                <Icon v-if="answers[q.id] === opt" name="check" :size="14" class="shrink-0" />{{ opt }}
                            </label>
                        </div>

                        <div v-else-if="q.type === 'number'" class="mt-3 flex max-w-xs items-center gap-2">
                            <input :id="`a-${q.id}`" v-model.trim="answers[q.id]" type="text" inputmode="decimal" class="field font-mono" maxlength="20"
                                   :aria-label="q.text" :aria-invalid="!!errors[`answers.${q.id}`]" @blur="onBlur" @input="delete errors[`answers.${q.id}`]" />
                            <span v-if="q.unit" class="shrink-0 text-sm text-ink-muted">{{ q.unit }}</span>
                        </div>

                        <div v-else-if="q.type === 'text'" class="mt-3">
                            <textarea :id="`a-${q.id}`" v-model="answers[q.id]" rows="5" class="field resize-y" :maxlength="TEXT_MAX"
                                      :aria-label="q.text" :aria-invalid="!!errors[`answers.${q.id}`]" @blur="onBlur" @input="delete errors[`answers.${q.id}`]"></textarea>
                            <div class="hint text-right font-mono">{{ (answers[q.id] || '').length }} / {{ TEXT_MAX }}</div>
                        </div>

                        <p v-if="errors[`answers.${q.id}`]" class="error" role="alert">{{ errors[`answers.${q.id}`] }}</p>
                    </fieldset>
                </div>
            </section>

            <section class="glass glass-strong p-6 sm:p-8">
                <h2 class="text-lg font-semibold">Declaration</h2>
                <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                    <strong class="text-ink">Privacy notice.</strong> We collect this information only to assess eligibility to pitch to Hexamatics.
                    It is processed under Nepal's Individual Privacy Act, 2075, seen only by authorised Hexamatics staff, and deleted after the retention period.
                </p>
                <div class="mt-4" :data-invalid="!!errors.declaration">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="declaration" type="checkbox" class="mt-1 h-5 w-5 shrink-0 accent-[var(--hexa-accent)]" @change="delete errors.declaration" />
                        <span class="text-[15px]">I confirm the answers are accurate and may be verified by Hexamatics.</span>
                    </label>
                    <p v-if="errors.declaration" class="error" role="alert">{{ errors.declaration }}</p>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" class="btn btn-secondary" :disabled="preview || saveState === 'saving'" @click="saveNow">
                        Save and continue later
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        {{ submitting ? 'Submitting…' : 'Submit questionnaire' }}
                    </button>
                </div>
                <p class="hint mt-3">After you submit, your answers can no longer be changed.</p>
            </section>
        </form>
    </VendorLayout>
</template>
