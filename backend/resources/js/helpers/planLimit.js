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
    return (Array.isArray(usage) ? usage : []).filter((row) => row && row.limit !== null && row.limit !== undefined);
}

/** The highest level among the rows: drives the tone of the whole widget and the upgrade button. */
export function worstUsageLevel(rows) {
    const order = ['none', 'ok', 'warn', 'full'];
    return rows.reduce((worst, row) => {
        const level = usageLevel(row.percent);
        return order.indexOf(level) > order.indexOf(worst) ? level : worst;
    }, 'none');
}
