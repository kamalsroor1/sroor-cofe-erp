// Dashboard display rules. Money values are decimal strings from the server, so nothing here does arithmetic.

/** True when the API returned a value for this metric (null / undefined / '' mean "not allowed or not computed"). */
export const hasMetric = (value) => value !== null && value !== undefined && value !== '';

/** True when a decimal string (or number) is a real, non-zero amount. */
export const isNonZeroAmount = (value) => hasMetric(value) && !/^[-+]?0*\.?0*$/.test(String(value).trim());

/**
 * A shop that never sold anything: no invoices listed, and no sales figure in the payload.
 * Used to show an invitation to make the first sale instead of an empty chart.
 */
export const isEmptyShop = ({ metrics = {}, recentInvoices = [], period = {} } = {}) =>
    recentInvoices.length === 0 &&
    Number(metrics.today_invoices_count || 0) === 0 &&
    !isNonZeroAmount(metrics.today_sales) &&
    !isNonZeroAmount(metrics.monthly_sales) &&
    !isNonZeroAmount(period.sales);
