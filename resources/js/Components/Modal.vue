<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ open: Boolean, title: String, wide: Boolean });
const emit = defineEmits(['close']);
const panel = ref(null);
let lastFocus = null;

function onKey(e) {
    if (e.key === 'Escape') emit('close');
    if (e.key === 'Tab' && panel.value) {
        const f = panel.value.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
        else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    }
}

watch(() => props.open, async (open) => {
    if (open) {
        lastFocus = document.activeElement;
        document.addEventListener('keydown', onKey);
        await nextTick();
        panel.value?.querySelector('input, select, textarea, button:not([data-close])')?.focus();
    } else {
        document.removeEventListener('keydown', onKey);
        lastFocus?.focus?.();
    }
});
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6">
                <div class="scrim absolute inset-0" @click="emit('close')" aria-hidden="true"></div>
                <div ref="panel" role="dialog" aria-modal="true" :aria-label="title"
                     class="modal-panel relative max-h-[92vh] w-full overflow-y-auto p-6 sm:rounded-[var(--radius-lg)]"
                     :class="wide ? 'sm:max-w-3xl' : 'sm:max-w-lg'">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <h2 class="text-lg font-semibold">{{ title }}</h2>
                        <button type="button" data-close class="btn btn-ghost !min-h-[36px] !px-2" @click="emit('close')" aria-label="Close">
                            <Icon name="x" />
                        </button>
                    </div>
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.scrim { background: var(--scrim); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
.modal-panel {
    background: var(--glass-bg-strong);
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow), inset 0 1px 0 var(--glass-highlight);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
@media (min-width: 640px) { .modal-panel { border-radius: var(--radius-lg); } }
.modal-enter-active, .modal-leave-active { transition: opacity .2s ease-out; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform .2s ease-out; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel { transform: translateY(8px); }
@media (prefers-reduced-motion: reduce) { .modal-enter-active, .modal-leave-active, .modal-enter-active .modal-panel { transition: none; } }
</style>
