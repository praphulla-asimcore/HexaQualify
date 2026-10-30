import { reactive } from 'vue';

export const toasts = reactive([]);
let id = 0;

export function toast(message, kind = 'info', ms = 4500) {
    if (!message) return;
    const t = { id: ++id, message, kind };
    toasts.push(t);
    setTimeout(() => {
        const i = toasts.indexOf(t);
        if (i >= 0) toasts.splice(i, 1);
    }, ms);
}
