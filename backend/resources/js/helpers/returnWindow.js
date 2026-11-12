// SET-02: the sales-return window, computed on the browser the same way the server does it
// (App\Services\Returns\ReturnWindowGuard): a return is accepted while
//   (today's business date - the invoice's business date) <= returns_max_days
// where both dates are tenant business dates (timezone + business-day cutoff). The server stays the
// judge; this only tells the cashier before they submit.
import { addDays } from './businessDate.js';

const DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})/;

function dayNumber(dateStr) {
    const match = DATE_PATTERN.exec(String(dateStr ?? ''));
    if (!match) return null;
    return Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])) / 86_400_000;
}

/** The configured window as a whole number of days, or null when there is no limit. */
export function normalizeReturnsMaxDays(value) {
    if (value === null || value === undefined || value === '') return null;
    const days = Number(value);
    return Number.isInteger(days) && days > 0 ? days : null;
}

/**
 * @param {Object} input
 * @param {string} input.invoiceDate invoice business date (YYYY-MM-DD)
 * @param {string} input.today current business date (YYYY-MM-DD)
 * @param {number|string|null} input.maxDays `returns_max_days` (null / empty = no limit)
 * @returns {{ limited: boolean, expired: boolean, ageDays: number|null, daysLeft: number|null, lastDay: string|null }}
 */
export function returnWindowStatus({ invoiceDate, today, maxDays }) {
    const limit = normalizeReturnsMaxDays(maxDays);
    const from = dayNumber(invoiceDate);
    const to = dayNumber(today);
    if (limit === null || from === null || to === null) {
        return { limited: false, expired: false, ageDays: null, daysLeft: null, lastDay: null };
    }
    const ageDays = to - from;
    return {
        limited: true,
        expired: ageDays > limit,
        ageDays,
        daysLeft: Math.max(limit - ageDays, 0),
        lastDay: addDays(invoiceDate, limit),
    };
}
