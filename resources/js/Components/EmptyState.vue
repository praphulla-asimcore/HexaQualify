<script setup>
defineProps({ title: String, text: String });
</script>

<template>
    <div class="glass glass-strong flex flex-col items-center px-6 py-12 text-center">
        <svg width="72" height="72" viewBox="0 0 72 72" fill="none" aria-hidden="true">
            <path d="M36 6 62 21v30L36 66 10 51V21L36 6Z" stroke="var(--hexa-accent)" stroke-width="2" stroke-linejoin="round" />
            <path d="M36 20 49 27.5v15L36 50l-13-7.5v-15L36 20Z" stroke="var(--hexa-secondary)" stroke-width="1.5" stroke-linejoin="round" opacity=".7" />
        </svg>
        <h3 class="mt-4 text-lg font-semibold">{{ title }}</h3>
        <p v-if="text" class="mt-1 max-w-sm text-sm text-ink-muted">{{ text }}</p>
        <div class="mt-5"><slot /></div>
    </div>
</template>
