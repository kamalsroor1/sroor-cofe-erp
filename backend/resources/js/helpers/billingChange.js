import { compareDecimalStrings, isValidDecimal } from './billing.js';
import { canRequestRenewal } from './billingRenewal.js';

export const CHANGE_ACTIONS = Object.freeze({ UPGRADE: 'upgrade', RENEW: 'renew' });

const priceOf = (plan, cycle) => (cycle === 'yearly' ? plan?.price_yearly : plan?.price_monthly);

/** Billing cycle the screen prices in: the running subscription's cycle, monthly otherwise. */
export const currentCycle = (data) => (data?.subscription?.billing_cycle?.value === 'yearly' ? 'yearly' : 'monthly');

/** #232 UI 1.1: no paid term running (trial / expired / free): the flow is "pick your plan". */
export const isSubscribeFlow = (data) => data?.subscription?.has_paid_term === false;

/** The renew/upgrade switch is shown only when the server allows a renewal invoice at all. */
export const showsActionSwitch = (data) => canRequestRenewal(data);

/**
 * Plans the shop may upgrade to. With a paid term: only plans priced above the current one in the
 * active cycle (never the current or a lower plan). Without one: every paid plan except the current.
 */
export const upgradeCandidates = (data, cycle = currentCycle(data)) => {
    const plans = (data?.available_plans || []).filter((plan) => !plan.is_free && !plan.is_current);
    if (isSubscribeFlow(data)) return plans;
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

/** Query for GET /billing/change-preview; plan_id and renew exist only for an upgrade. */
export const previewQuery = ({ action, planId, cycle, renew }) =>
    action === CHANGE_ACTIONS.UPGRADE
        ? { action, plan_id: planId, cycle, renew: renew ? 1 : 0 }
        : { action: CHANGE_ACTIONS.RENEW, cycle };

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
