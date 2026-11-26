// #232 UI §3: describes the 48-hour temporary-access banner from GET /billing/subscription `temporary_access`.

const LAST_HOURS_SECONDS = 6 * 3600;
const DISMISS_PREFIX = 'billing.grace.dismissed.';

const pad = (n) => String(n).padStart(2, '0');

/** HH:MM from a number of seconds (never negative). */
export const formatCountdown = (seconds) => {
    const total = Math.max(0, Math.floor(Number(seconds) || 0));
    return `${pad(Math.floor(total / 3600))}:${pad(Math.floor((total % 3600) / 60))}`;
};

/**
 * Seconds left. The server's `seconds_left` minus the time since it was fetched wins (no dependency on
 * the device clock); without a fetch time the `ends_at` instant is compared with `nowMs`.
 */
export const secondsLeft = (window, nowMs, fetchedAtMs = null) => {
    if (!window) return 0;
    if (fetchedAtMs !== null && Number.isFinite(Number(window.seconds_left))) {
        const elapsed = Math.max(0, Math.floor((nowMs - fetchedAtMs) / 1000));
        return Math.max(0, Number(window.seconds_left) - elapsed);
    }
    const end = Date.parse(window.ends_at || '');
    return Number.isFinite(end) ? Math.max(0, Math.floor((end - nowMs) / 1000)) : 0;
};

export const isDismissed = (invoiceId, storage) => {
    try {
        return storage.getItem(`${DISMISS_PREFIX}${invoiceId}`) === '1';
    } catch {
        return false;
    }
};

export const dismiss = (invoiceId, storage) => {
    try {
        storage.setItem(`${DISMISS_PREFIX}${invoiceId}`, '1');
    } catch {
        // Private mode / quota: the banner simply comes back on the next load.
    }
};

/**
 * @param {object|null} window  `subscription_temporary_access` of /system/context or `temporary_access` of /billing/subscription
 * @param {{nowMs:number, fetchedAtMs?:number|null, planName?:string, endsAt?:string|null, dismissed?:boolean}} ctx
 * @returns {{state:string, tone:string, textKey:string, params:object, countdown:string|null, action:string|null, dismissible:boolean}|null}
 */
export const describeTemporaryAccess = (window, ctx) => {
    if (!window) return null;
    const base = { params: {}, countdown: null, action: null, dismissible: false };

    switch (window.state) {
        case 'active': {
            const left = secondsLeft(window, ctx.nowMs, ctx.fetchedAtMs ?? null);
            if (left <= 0)
                return { ...base, state: 'expired', tone: 'neutral', textKey: 'subscription.billing.grace.expired' };
            const last = left <= LAST_HOURS_SECONDS;
            return {
                ...base,
                state: last ? 'last_hours' : 'active',
                tone: last ? 'warning' : 'info',
                textKey: last ? 'subscription.billing.grace.last_hours' : 'subscription.billing.grace.active',
                countdown: formatCountdown(left),
                action: 'details',
            };
        }
        case 'expired':
            return { ...base, state: 'expired', tone: 'neutral', textKey: 'subscription.billing.grace.expired' };
        case 'rejected':
            return {
                ...base,
                state: 'rejected',
                tone: 'danger',
                textKey: 'subscription.billing.grace.rejected',
                params: { reason: window.rejection_reason || '' },
                action: 'upload_again',
            };
        case 'verified': {
            if (ctx.dismissed) return null;
            const upgrade = window.invoice_type === 'upgrade';
            return {
                ...base,
                state: 'verified',
                tone: 'success',
                textKey: upgrade
                    ? 'subscription.billing.grace.confirmed_upgrade'
                    : 'subscription.billing.grace.confirmed_renewal',
                // The window names the plan it billed and the period end (#232 item 9); ctx is the fallback.
                params: { plan: window.plan?.name || ctx.planName || '', end: window.period_end || ctx.endsAt || '' },
                dismissible: true,
            };
        }
        default:
            return null;
    }
};
