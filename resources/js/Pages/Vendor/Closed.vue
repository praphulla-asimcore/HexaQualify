<script setup>
import { computed } from 'vue';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({ reason: String });
const copy = computed(() => ({
    expired: { title: 'This link has expired', text: 'The deadline for this questionnaire has passed, so it can no longer be opened or submitted. If you still wish to be considered, please contact your Hexamatics contact to request a new invitation.', icon: 'clock' },
    revoked: { title: 'This invitation has been withdrawn', text: 'This link is no longer active. If you think this is a mistake, please contact your Hexamatics contact.', icon: 'ban' },
    submitted: { title: 'Already submitted', text: 'This questionnaire has already been submitted and can no longer be changed. Thank you.', icon: 'check' },
    invalid: { title: 'Link not recognised', text: 'This link is not valid. Please check that you copied the full link from the invitation email, or ask your Hexamatics contact for a new one.', icon: 'alert' },
}[props.reason] ?? { title: 'Unavailable', text: '', icon: 'alert' }));
</script>

<template>
    <VendorLayout :title="copy.title">
        <section class="glass glass-strong rise flex flex-col items-center p-8 text-center sm:p-12">
            <span class="grid h-16 w-16 place-items-center rounded-full bg-[var(--brand-tint-strong)] text-brand-accent"><Icon :name="copy.icon" :size="28" /></span>
            <h1 class="mt-5 text-2xl font-semibold">{{ copy.title }}</h1>
            <p class="mt-3 max-w-md text-ink-muted">{{ copy.text }}</p>
        </section>
    </VendorLayout>
</template>
