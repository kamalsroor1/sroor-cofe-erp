/**
 * Without the reports.advanced plan feature the server strips cost/profit keys from the P&L
 * summary (App\Support\ProfitVisibility). A missing key means "not in your plan", never zero
 * (demo trial card #216 showed COGS, margin and net profit as 0). An empty summary (not loaded
 * yet, or a failed load) is not "stripped".
 *
 * @param {Record<string, unknown> | null | undefined} summary
 * @param {string} key
 */
export function isStrippedFigure(summary, key) {
    if (!summary || typeof summary !== 'object' || Object.keys(summary).length === 0) {
        return false;
    }
    return !Object.prototype.hasOwnProperty.call(summary, key);
}
