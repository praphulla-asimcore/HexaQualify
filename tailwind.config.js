/** @type {import('tailwindcss').Config} */
// All component colours come from the CSS tokens in resources/css/tokens.css (§10.2).
export default {
    content: ['./resources/views/**/*.blade.php', './resources/js/**/*.{vue,js}'],
    darkMode: ['selector', '[data-theme="dark"]'],
    theme: {
        extend: {
            colors: {
                brand: {
                    primary: 'var(--hexa-primary)',
                    secondary: 'var(--hexa-secondary)',
                    accent: 'var(--hexa-accent)',
                },
                ink: { DEFAULT: 'var(--text)', muted: 'var(--text-muted)', onbrand: 'var(--text-on-brand)' },
                qualified: { DEFAULT: 'var(--qualified)', bg: 'var(--qualified-bg)' },
                notq: { DEFAULT: 'var(--not-qualified)', bg: 'var(--not-qualified-bg)' },
                review: { DEFAULT: 'var(--review)', bg: 'var(--review-bg)' },
                line: 'var(--line)',
            },
            borderRadius: { sm: 'var(--radius-sm)', md: 'var(--radius-md)', lg: 'var(--radius-lg)' },
            fontFamily: {
                display: ['Sora', 'system-ui', 'sans-serif'],
                sans: ['"Plus Jakarta Sans"', 'system-ui', 'Segoe UI', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },
            maxWidth: { portal: '760px' },
        },
    },
    plugins: [],
};
