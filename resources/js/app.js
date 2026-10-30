import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { progress } from './lib/progress';

createInertiaApp({
    title: (title) => (title ? `${title} · HexaQualify` : 'HexaQualify'),
    // Lazy chunks: the vendor portal only downloads the vendor pages.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    // Inertia's built-in progress bar injects an inline <style>, which our CSP blocks.
    progress: false,
});

router.on('start', () => progress.start());
router.on('finish', () => progress.done());
