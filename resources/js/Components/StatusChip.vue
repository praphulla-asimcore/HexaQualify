<script setup>
import { computed } from 'vue';
import { STATUS_LABELS } from '../lib/format';

const props = defineProps({ status: String });
const cls = computed(() => ({
    decided: 'badge-pass', scored: 'badge-pass', submitted: 'badge-pass',
    expired: 'badge-fail', revoked: 'badge-fail',
    in_progress: 'badge-review', opened: 'badge-review',
}[props.status] ?? 'badge-neutral'));
</script>

<template>
    <span class="badge" :class="cls">{{ STATUS_LABELS[status] ?? status }}</span>
</template>
