<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import HexaLockup from '../Components/HexaLockup.vue';

const props = defineProps({ status: Number });
const copy = computed(() => ({
    403: ['Not allowed', 'Your role does not have access to this page.'],
    404: ['Page not found', 'The page you are looking for does not exist.'],
    419: ['Session expired', 'Please refresh the page and try again.'],
    429: ['Too many requests', 'Please wait a minute and try again.'],
    500: ['Something went wrong', 'We have been notified. Please try again shortly.'],
    503: ['Down for maintenance', 'Please check back soon.'],
}[props.status] ?? ['Error', '']));
</script>

<template>
    <Head :title="copy[0]" />
    <main class="flex min-h-screen items-center justify-center px-4">
        <div class="glass glass-strong rise w-full max-w-md p-8 text-center">
            <HexaLockup :height="24" />
            <p class="mt-8 font-mono text-sm text-ink-muted">{{ status }}</p>
            <h1 class="mt-1 text-2xl font-semibold">{{ copy[0] }}</h1>
            <p class="mt-2 text-ink-muted">{{ copy[1] }}</p>
            <Link href="/" class="btn btn-secondary mt-6">Go to dashboard</Link>
        </div>
    </main>
</template>
