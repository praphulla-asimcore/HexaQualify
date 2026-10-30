<script setup>
import { computed } from 'vue';
import { RESULT_LABELS, resultKind } from '../lib/format';

const props = defineProps({ result: { type: String, default: null }, large: Boolean, override: Boolean });
const kind = computed(() => resultKind(props.result));
const label = computed(() => (props.result ? RESULT_LABELS[props.result] ?? props.result : 'Not scored'));
</script>

<template>
    <span v-if="!large" class="badge" :class="result ? `badge-${kind}` : 'badge-neutral'">
        {{ label }}<span v-if="override" class="font-normal opacity-80">· override</span>
    </span>
    <span v-else class="verdict glass inline-flex items-center gap-3 px-5 py-3" :class="`verdict-${kind}`">
        <span class="h-3 w-3 rounded-full bg-current" aria-hidden="true"></span>
        <span class="font-display text-xl font-semibold">{{ label }}</span>
    </span>
</template>

<style scoped>
.verdict { border-radius: 999px; }
.verdict-pass { color: var(--qualified); border-color: var(--qualified); box-shadow: 0 0 24px var(--qualified-bg), var(--glass-shadow); }
.verdict-fail { color: var(--not-qualified); border-color: var(--not-qualified); box-shadow: 0 0 24px var(--not-qualified-bg), var(--glass-shadow); }
.verdict-review { color: var(--review); border-color: var(--review); box-shadow: 0 0 24px var(--review-bg), var(--glass-shadow); }
</style>
