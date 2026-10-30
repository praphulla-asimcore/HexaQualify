<script setup>
import { computed } from 'vue';
import { formatPct } from '../lib/format';

// Glass track, brand-gradient fill, vertical pass-mark marker with its label (§10.3).
// Widths are bound through the CSSOM (:style), which a strict CSP allows.
const props = defineProps({
    pct: { type: Number, default: null },
    passMark: { type: Number, required: true },
    compact: Boolean,
});
const fill = computed(() => Math.max(0, Math.min(100, props.pct ?? 0)));
const mark = computed(() => Math.max(0, Math.min(100, props.passMark)));
</script>

<template>
    <div class="w-full" role="img" :aria-label="`Score ${formatPct(pct)} against a pass mark of ${passMark}%`">
        <div v-if="!compact" class="mb-2 flex items-baseline justify-between text-sm">
            <span class="font-mono text-base font-semibold text-ink">{{ formatPct(pct) }}</span>
            <span class="text-ink-muted">Pass mark {{ passMark }}%</span>
        </div>
        <div class="track relative" :class="compact ? 'h-2' : 'h-3'">
            <div class="fill absolute inset-y-0 left-0" :style="{ width: fill + '%' }"></div>
            <div class="marker absolute" :style="{ left: mark + '%' }" :title="`Pass mark ${passMark}%`"></div>
        </div>
    </div>
</template>

<style scoped>
.track { background: var(--glass-bg-strong); border: 1px solid var(--line); border-radius: 999px; }
.fill { background: linear-gradient(90deg, var(--hexa-secondary), var(--hexa-accent)); border-radius: 999px; transition: width .25s ease-out; }
.marker { top: -4px; bottom: -4px; width: 2px; margin-left: -1px; background: var(--text); border-radius: 2px; }
@media (prefers-reduced-motion: reduce) { .fill { transition: none; } }
</style>
