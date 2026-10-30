<script setup>
defineProps({ label: String, value: [Number, String], tone: { type: String, default: 'brand' } });
</script>

<template>
    <div class="glass lift rise p-5">
        <div class="flex items-center gap-2 text-sm text-ink-muted">
            <span class="h-2.5 w-2.5 rounded-full" :class="{
                'bg-qualified': tone === 'pass', 'bg-notq': tone === 'fail', 'bg-review': tone === 'review', 'bg-brand-accent': tone === 'brand',
            }" aria-hidden="true"></span>
            {{ label }}
        </div>
        <div class="mt-2 font-display text-[32px] font-semibold leading-none text-ink">{{ value }}</div>
    </div>
</template>
