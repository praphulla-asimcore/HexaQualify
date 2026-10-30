<script setup>
import { ref } from 'vue';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import Icon from '../../Components/Icon.vue';
import { formatDate } from '../../lib/format';

defineProps({ questionnaire: Object, vendor: Object, answers: Object, submittedAt: String });
const showAnswers = ref(false);
const vendorLabels = { company: 'Company name', pan: 'PAN/VAT number', contact_person: 'Contact person', designation: 'Designation', email: 'Email', phone: 'Phone' };
const display = (q, a) => (a === undefined || a === null || a === '' ? '—' : q.type === 'yesno' ? (a === 'yes' ? 'Yes' : 'No') : q.type === 'number' ? `${a} ${q.unit ?? ''}` : a);
</script>

<template>
    <VendorLayout title="Submitted">
        <section class="glass glass-strong rise flex flex-col items-center p-8 text-center sm:p-12">
            <span class="check-circle grid h-24 w-24 place-items-center rounded-full">
                <Icon name="check" :size="44" class="text-qualified" />
            </span>
            <h1 class="mt-6 text-2xl font-semibold sm:text-[28px]">Thank you — your questionnaire has been submitted to Hexamatics.</h1>
            <p class="mt-3 max-w-md text-ink-muted">We have emailed you a confirmation. Our team will review your answers and contact you about next steps.</p>
            <p class="mt-2 text-sm text-ink-muted">Submitted {{ formatDate(submittedAt, true) }} (Nepal time)</p>
            <button type="button" class="btn btn-secondary mt-6" @click="showAnswers = !showAnswers" :aria-expanded="showAnswers">
                <Icon :name="showAnswers ? 'chevronUp' : 'eye'" :size="16" />{{ showAnswers ? 'Hide' : 'View' }} your answers
            </button>
        </section>

        <section v-if="showAnswers" class="glass glass-strong mt-4 p-6 sm:p-8">
            <h2 class="text-lg font-semibold">Your answers (read-only)</h2>
            <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                <div v-for="(label, key) in vendorLabels" :key="key"><dt class="text-xs text-ink-muted">{{ label }}</dt><dd class="font-medium">{{ vendor[key] || '—' }}</dd></div>
            </dl>
            <div v-for="section in questionnaire.structure.sections" :key="section.title" class="mt-6">
                <h3 class="font-semibold">{{ section.title }}</h3>
                <dl class="mt-2 divide-y divide-[var(--line)]">
                    <div v-for="q in section.questions" :key="q.id" class="py-3">
                        <dt class="text-sm text-ink-muted">{{ q.text }}</dt>
                        <dd class="mt-1 whitespace-pre-line font-medium">{{ display(q, answers[q.id]) }}</dd>
                    </div>
                </dl>
            </div>
        </section>
    </VendorLayout>
</template>

<style scoped>
.check-circle { background: var(--qualified-bg); border: 1px solid var(--glass-border); box-shadow: 0 0 32px var(--qualified-bg), inset 0 1px 0 var(--glass-highlight); }
</style>
