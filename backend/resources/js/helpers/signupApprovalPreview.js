// SELL-06: what the approve dialog shows before the operator confirms. The server sends
// `approval_preview` for the requested plan; when the operator picks another plan the trial
// length is recomputed with the same rule the server applies (plan.trial_days, else the default).

const DAY_MS = 86_400_000;

/** The trial length a plan gets: its own `trial_days`, else the platform default. */
export function trialDaysFor(planTrialDays, defaultTrialDays) {
    const own = Number(planTrialDays);
    return Number.isFinite(own) && own > 0 ? own : Number(defaultTrialDays) || 0;
}

/**
 * @param {object|null} preview  `approval_preview` of the signup request
 * @param {string} planCode      plan chosen in the dialog (slug)
 * @param {Array<{value:string,label:string,trialDays:number}>} plans  dialog plan options
 * @param {Date} [now]
 * @returns {{planName:string,trialDays:number,endsAt:string|null,tenantStatus:string,subscriptionStatus:string}|null}
 */
export function resolveApprovalTerms(preview, planCode, plans = [], now = new Date()) {
    if (!preview) return null;
    const base = {
        planName: preview.plan?.name || '',
        trialDays: Number(preview.trial_days) || 0,
        endsAt: preview.trial_ends_at || null,
        tenantStatus: preview.tenant_status?.label || '',
        subscriptionStatus: preview.subscription_status?.label || '',
        matchingTenants: Array.isArray(preview.matching_tenants) ? preview.matching_tenants : [],
    };
    if (!planCode || planCode === preview.plan?.code) return base;

    const chosen = plans.find((plan) => plan.value === planCode);
    if (!chosen) return base;
    const trialDays = trialDaysFor(chosen.trialDays, preview.default_trial_days);
    return {
        ...base,
        planName: chosen.label,
        trialDays,
        endsAt: new Date(now.getTime() + trialDays * DAY_MS).toISOString(),
    };
}
