<script setup>
import Icon from './Icon.vue';
import { toasts } from '../lib/toast';
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-5 z-[70] flex flex-col items-center gap-2 px-4" aria-live="polite" role="status">
        <TransitionGroup name="toast">
            <div v-for="t in toasts" :key="t.id"
                 class="toast pointer-events-auto flex max-w-md items-center gap-2.5 rounded-full px-4 py-2.5 text-sm font-medium text-ink">
                <Icon :name="t.kind === 'error' ? 'alert' : 'check'" :class="t.kind === 'error' ? 'text-notq' : 'text-qualified'" />
                <span>{{ t.message }}</span>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast {
    background: var(--glass-bg-strong);
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
}
.toast-enter-active, .toast-leave-active { transition: opacity .2s ease-out, transform .2s ease-out; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }
</style>
