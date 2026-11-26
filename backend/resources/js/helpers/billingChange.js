import { compareDecimalStrings, isValidDecimal } from './billing.js';
import { canRequestRenewal } from './billingRenewal.js';

export const CHANGE_ACTIONS = Object.freeze({ UPGRADE: 'upgrade', RENEW: 'renew', SUBSCRIBE: 'subscribe' });

const priceOf = (plan, cycle) => (cycle === 'yearly' ? plan?.price_yearly : plan?.price_monthly);

/** Billing cycle the screen prices in: the running subscription's cycle, monthly otherwise. */
export const currentCycle = (data) => (data?.subscription?.billing_cycle?.value === 'yearly' ? 'yearly' : 'monthly');

/** #232 UI 1.1: no paid term running (trial / expired / free): the flow is "pick your plan". */
export const isSubscribeFlow = (data) => data?.subscription?.has_paid_term === false;

/** The renew/upgrade switch is shown only when the server allows a renewal invoice at all. */
export const showsActionSwitch = (data) => canRequestRenewal(data);

/**
 * Plans the shop may move to. With a paid term: only plans priced above the current one in the
 * active cycle (never the current or a lower plan). Without one (trial / lapsed): every paid plan,
 * the shop's own included, because it may subscribe to the plan it is on.
 */
export const upgradeCandidates = (data, cycle = currentCycle(data)) => {
    if (isSubscribeFlow(data)) return (data?.available_plans || []).filter((plan) => !plan.is_free);
    const plans = (data?.available_plans || []).filter((plan) => !plan.is_free && !plan.is_current);
    const current = (data?.available_plans || []).find((plan) => plan.is_current);
    const base = priceOf(current, cycle);
    if (!current || !isValidDecimal(base)) return plans;
    return plans.filter((plan) => {
        const price = priceOf(plan, cycle);
        return isValidDecimal(price) && compareDecimalStrings(price, base) > 0;
    });
};

/** Default action: upgrade when a higher plan exists (or in the subscribe flow), else renew. */
export const defaultAction = (data) =>
    !showsActionSwitch(data) || upgradeCandidates(data).length > 0 ? CHANGE_ACTIONS.UPGRADE : CHANGE_ACTIONS.RENEW;

/** The action the API knows: the screen's "upgrade" is a "subscribe" when no paid term is running. */
export const apiAction = ({ action, subscribe = false }) => {
    if (action !== CHANGE_ACTIONS.UPGRADE) return CHANGE_ACTIONS.RENEW;
    return subscribe ? CHANGE_ACTIONS.SUBSCRIBE : CHANGE_ACTIONS.UPGRADE;
};

/** Query for GET /billing/change-preview; plan_id and renew exist only for an upgrade / subscribe. */
export const previewQuery = ({ action, planId, cycle, renew, subscribe = false }) => {
    const effective = apiAction({ action, subscribe });
    return effective === CHANGE_ACTIONS.RENEW
        ? { action: effective, cycle }
        : { action: effective, plan_id: planId, cycle, renew: renew && !subscribe ? 1 : 0 };
};

/** Body of POST /billing/change-invoice (upgrade or subscribe only). */
export const changeInvoiceBody = ({ action, planId, cycle, renew, subscribe = false }) => ({
    action: apiAction({ action, subscribe }),
    plan_id: planId,
    cycle,
    renew: renew && !subscribe,
});

const REFUSAL_CODES = [
    'billing.request_plan_unavailable',
    'billing.free_plan_not_billable',
    'billing.request_same_plan',
    'billing.upgrade_not_higher',
    'billing.request_already_pending',
];

/**
 * Maps the answer of POST /billing/change-invoice to what the screen does next:
 *  - invoice: 201 with an invoice, open its payment screen;
 *  - held:    201 without one (agreed founder price), show the held state;
 *  - duplicate: 409 billing.invoice_duplicate, open the existing invoice the payload points to;
 *  - refused: one of the 422 codes, show the server's (translated) message and re-read the state;
 *  - failed:  anything else (the interceptor's message is shown as is).
 * @param {{status?:number, data?:object}|null} response  axios response, or `error.response`
 */
export const changeOutcome = (response) => {
    const status = response?.status ?? null;
    const body = response?.data || {};
    if (status === 201) {
        const invoice = body.data?.invoice || null;
        if (invoice?.id) return { type: 'invoice', invoiceId: invoice.id, message: body.message || '' };
        if (body.data?.held_for_price_review === true) return { type: 'held', message: body.message || '' };
        return { type: 'failed', message: '' };
    }
    if (status === 409 && body.error_code === 'billing.invoice_duplicate') {
        return {
            type: 'duplicate',
            invoiceId: body.details?.invoice_id ?? null,
            number: body.details?.invoice_number ?? '',
        };
    }
    if (status === 422 && REFUSAL_CODES.includes(body.error_code)) {
        return { type: 'refused', code: body.error_code, message: body.message || '' };
    }
    return { type: 'failed', message: body.message || '' };
};

/** True when the preview can be requested: an upgrade needs a chosen plan. */
export const canPreview = ({ action, planId }) => action === CHANGE_ACTIONS.RENEW || !!planId;

/** Timeline steps of the preview: today, the (unchanged) current end, the new end when one exists. */
export const timelineSteps = (preview) => {
    if (!preview) return [];
    const steps = [{ key: 'today' }];
    if (preview.period_end) steps.push({ key: 'end_fixed', date: preview.period_end });
    if (preview.new_period_end) steps.push({ key: 'new_end', date: preview.new_period_end });
    return steps.length > 1 ? steps : [];
};

/** Which explanatory note sits under the totals (none for a plain subscribe). */
export const previewNoteKey = (preview) => {
    if (preview?.kind === 'upgrade' || preview?.kind === 'upgrade_renewal') return 'note_upgrade';
    if (preview?.kind === 'renewal') return 'note_renew';
    return null;
};

/** Label key of the primary CTA. */
export const ctaKey = ({ held, action, subscribe }) => {
    if (held) return 'cta_send_review';
    if (action === CHANGE_ACTIONS.UPGRADE && subscribe) return 'cta_subscribe';
    return 'cta_issue';
};

/** Translation key of the "renew too" switch for the cycle. */
export const renewTooKey = (cycle) => (cycle === 'yearly' ? 'renew_too_year' : 'renew_too_month');

/** Translation key of a plan's price for the cycle. */
export const priceKey = (cycle) => (cycle === 'yearly' ? 'price_per_year' : 'price_per_month');

/** Price of a plan in the cycle (a decimal string from the server). */
export const planPrice = priceOf;

const LAPSED_STATUSES = ['expired', 'past_due', 'suspended', 'cancelled', 'archived'];

/** Status chip of the plan card: { key (billing.change.status.*), tone }. */
export const planStatus = (data) => {
    const status = data?.tenant_status?.value;
    if (data?.tenant_status?.temporary_access === true) return { key: 'grace', tone: 'warning' };
    if ((data?.tenant_status?.access || 'full') !== 'full' || LAPSED_STATUSES.includes(status)) {
        return { key: 'expired', tone: 'danger' };
    }
    return status === 'trial' ? { key: 'trial', tone: 'info' } : { key: 'active', tone: 'success' };
};
