const TZ = 'Asia/Kathmandu';

export const RESULT_LABELS = {
    Pass: 'Qualified',
    Fail: 'Not qualified',
    Review: 'Needs review',
    Unscored: 'Needs review',
};

export function resultKind(result) {
    return result === 'Pass' ? 'pass' : result === 'Fail' ? 'fail' : 'review';
}

export function formatDate(iso, withTime = false) {
    if (!iso) return '—';
    const opts = { day: 'numeric', month: 'short', year: 'numeric', timeZone: TZ };
    if (withTime) Object.assign(opts, { hour: '2-digit', minute: '2-digit' });
    return new Intl.DateTimeFormat('en-GB', opts).format(new Date(iso));
}

export function formatPct(n) {
    return n === null || n === undefined ? '—' : `${Number(n).toFixed(1)}%`;
}

export function formatPoints(n) {
    if (n === null || n === undefined) return '—';
    return Number.isInteger(Number(n)) ? String(Number(n)) : Number(n).toFixed(1);
}

export const STATUS_LABELS = {
    draft: 'Draft',
    sent: 'Sent',
    opened: 'Opened',
    in_progress: 'In progress',
    submitted: 'Submitted',
    scored: 'Scored',
    decided: 'Decided',
    expired: 'Expired',
    revoked: 'Revoked',
};

export function csrfToken() {
    return decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}

/** JSON fetch with Laravel's XSRF header. */
export async function api(url, { method = 'GET', body } = {}) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(data.message || `Request failed (${res.status})`);
        err.status = res.status;
        err.data = data;
        throw err;
    }
    return data;
}
