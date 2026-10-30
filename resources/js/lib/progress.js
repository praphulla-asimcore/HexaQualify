// CSP-safe navigation progress bar (a single class-styled element, no inline styles).
let el = null;
let timer = null;

export const progress = {
    start() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (el) return;
            el = document.createElement('div');
            el.className = 'page-progress';
            el.setAttribute('role', 'progressbar');
            el.setAttribute('aria-label', 'Loading');
            document.body.appendChild(el);
        }, 180);
    },
    done() {
        clearTimeout(timer);
        el?.remove();
        el = null;
    },
};
