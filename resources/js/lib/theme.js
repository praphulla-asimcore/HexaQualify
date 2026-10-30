import { ref } from 'vue';

const KEY = 'hq-theme';
export const theme = ref(document.documentElement.getAttribute('data-theme') || 'light');

function apply(t) {
    theme.value = t;
    document.documentElement.setAttribute('data-theme', t);
}

export function toggleTheme() {
    const next = theme.value === 'dark' ? 'light' : 'dark';
    try {
        localStorage.setItem(KEY, next);
    } catch {}
    apply(next);
}

// Follow the OS setting until the user picks a theme manually.
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    let manual = null;
    try {
        manual = localStorage.getItem(KEY);
    } catch {}
    if (!manual) apply(e.matches ? 'dark' : 'light');
});
