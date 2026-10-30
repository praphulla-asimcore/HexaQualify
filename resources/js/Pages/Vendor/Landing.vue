<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import Icon from '../../Components/Icon.vue';
import { formatDate } from '../../lib/format';

const props = defineProps({
    token: String, questionnaire: Object, vendor_company: String, email: String,
    deadline: String, codeSent: Boolean, lockedUntil: String,
});
const send = useForm({});
const verify = useForm({ code: '' });
const sent = computed(() => props.codeSent || verify.hasErrors);
const codeInput = ref(null);

function requestCode() {
    send.post(`/v/${props.token}/otp`, { preserveScroll: true, onSuccess: () => setTimeout(() => codeInput.value?.focus(), 50) });
}
function submitCode() {
    verify.code = verify.code.replace(/\D/g, '').slice(0, 6);
    verify.post(`/v/${props.token}/otp/verify`, { preserveScroll: true });
}
</script>

<template>
    <VendorLayout :title="questionnaire.name">
        <section class="glass glass-strong rise p-6 sm:p-8">
            <p class="text-sm font-medium text-ink-muted">Pre-pitch screening for {{ vendor_company }}</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-[28px]">{{ questionnaire.name }}</h1>
            <p v-if="questionnaire.description" class="mt-2 text-ink-muted">{{ questionnaire.description }}</p>

            <div class="panel mt-6 grid gap-4 p-4 sm:grid-cols-2">
                <div class="flex items-start gap-3">
                    <Icon name="clock" class="mt-0.5 shrink-0 text-brand-accent" />
                    <div><div class="text-sm text-ink-muted">Please submit by</div><div class="font-semibold">{{ formatDate(deadline) }}</div></div>
                </div>
                <div class="flex items-start gap-3">
                    <Icon name="shield" class="mt-0.5 shrink-0 text-brand-accent" />
                    <div><div class="text-sm text-ink-muted">Verification code goes to</div><div class="font-mono font-semibold">{{ email }}</div></div>
                </div>
            </div>

            <ul class="mt-6 space-y-2 text-sm text-ink-muted">
                <li class="flex gap-2"><Icon name="check" :size="16" class="mt-0.5 shrink-0 text-qualified" />Most questions are yes/no or multiple choice. It takes about 15 minutes.</li>
                <li class="flex gap-2"><Icon name="check" :size="16" class="mt-0.5 shrink-0 text-qualified" />Your answers save automatically. You can come back to this link until the deadline.</li>
                <li class="flex gap-2"><Icon name="check" :size="16" class="mt-0.5 shrink-0 text-qualified" />Answers may be verified by Hexamatics before any pitch meeting.</li>
            </ul>

            <div v-if="lockedUntil" class="mt-6 flex items-start gap-3 rounded-[var(--radius-md)] bg-notq-bg p-4 text-sm" role="alert">
                <Icon name="lock" class="mt-0.5 shrink-0 text-notq" />
                <span>Too many incorrect codes. For your security, verification is paused until {{ formatDate(lockedUntil, true) }}.</span>
            </div>

            <div v-else-if="!sent" class="mt-8">
                <button type="button" class="btn btn-primary w-full sm:w-auto" :disabled="send.processing" @click="requestCode">
                    <Icon name="mail" :size="16" />{{ send.processing ? 'Sending…' : 'Email me a verification code' }}
                </button>
            </div>

            <form v-else class="mt-8" @submit.prevent="submitCode" novalidate>
                <label for="code" class="label">Enter the 6-digit code we emailed to {{ email }}</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="code" ref="codeInput" v-model="verify.code" class="field font-mono text-xl tracking-[0.4em] sm:max-w-[220px]"
                           inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="••••••" autofocus
                           :aria-invalid="!!verify.errors.code" aria-describedby="code-error code-hint" />
                    <button type="submit" class="btn btn-primary" :disabled="verify.processing || verify.code.replace(/\D/g, '').length !== 6">
                        {{ verify.processing ? 'Checking…' : 'Verify and continue' }}
                    </button>
                </div>
                <p v-if="verify.errors.code" id="code-error" class="error" role="alert">{{ verify.errors.code }}</p>
                <p id="code-hint" class="hint">The code is valid for 10 minutes.
                    <button type="button" class="font-semibold text-brand-accent underline underline-offset-2" :disabled="send.processing" @click="requestCode">Send a new code</button>
                </p>
            </form>
        </section>

        <section class="glass glass-strong mt-4 p-5 text-xs leading-relaxed text-ink-muted sm:px-8">
            <strong class="text-ink">Privacy notice.</strong>
            Hexamatics collects the information in this questionnaire only to assess whether your company is eligible to pitch.
            It is processed in line with Nepal's Individual Privacy Act, 2075, is accessible only to authorised Hexamatics staff,
            and is deleted after the retention period. Contact procurement@hexamatics.com for access or correction requests.
        </section>
    </VendorLayout>
</template>
