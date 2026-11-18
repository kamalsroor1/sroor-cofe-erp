// PLAN-13: shared rules of the plan-limit UX (dashboard widget, billing usage bars, POS banner, 403 alert).

export const LIMIT_REACHED_CODE = 'subscription.limit_reached';

/** From this share of the limit the bar turns yellow; at 100% it is red and new records are refused. */
export const USAGE_WARN_PERCENT = 80;
export const USAGE_FULL_PERCENT = 100;

/** True only for the plan-limit refusal (403 + error_code), never for another 403 (permissions, ...). */
export function isLimitReachedError(error) {
    return error?.response?.status === 403 && error.response.data?.error_code === LIMIT_REACHED_CODE;
}

/** Which resource a limit refusal names (`invoices_month`, `items`, ...), or null for any other error. */
export function limitResourceOf(error) {
    return isLimitReachedError(error) ? (error.response.data?.details?.limit ?? null) : null;
}

/** 'none' (unlimited / unknown) | 'ok' | 'warn' (>= 80%) | 'full' (>= 100%). */
export function usageLevel(percent) {
    if (percent === null || percent === undefined || Number.isNaN(Number(percent))) return 'none';
    if (percent >= USAGE_FULL_PERCENT) return 'full';
    if (percent >= USAGE_WARN_PERCENT) return 'warn';
    return 'ok';
}

/** Usage rows of limited resources only: an unlimited resource (NULL limit) is never shown. */
export function limitedRows(usage) {
    // limit 0 = the plan does not include this resource at all (e.g. vans on the free plan): no bar for it.
    return (Array.isArray(usage) ? usage : []).filter(
        (row) => row && row.limit !== null && row.limit !== undefined && Number(row.limit) > 0
    );
}

/**
 * Structural allowances (branches, warehouses, vans) are sized by the plan, not consumed by daily work:
 * a free shop's only branch is 1 of 1 from day one. They never raise the "limit reached" warning.
 * Mirrors LimitResource::isAllowance() on the server.
 */
export const ALLOWANCE_RESOURCES = Object.freeze(['stores', 'warehouses', 'vans']);

export function isAllowanceRow(row) {
    return ALLOWANCE_RESOURCES.includes(row?.resource);
}

/** The level a single row shows: an allowance that is fully used is normal, not a warning. */
export function rowUsageLevel(row) {
    const level = usageLevel(row?.percent);
    return isAllowanceRow(row) && level !== 'none' ? 'ok' : level;
}

/** The highest level among the rows: drives the tone of the whole widget and the upgrade button. */
export function worstUsageLevel(rows) {
    const order = ['none', 'ok', 'warn', 'full'];
    return rows.reduce((worst, row) => {
        if (isAllowanceRow(row)) return worst;
        const level = usageLevel(row.percent);
        return order.indexOf(level) > order.indexOf(worst) ? level : worst;
    }, 'none');
}
