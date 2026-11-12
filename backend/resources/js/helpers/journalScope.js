// ROLE-01: the daily journal / shift payloads hide figures (null) from a user without reports.view.
// Pure helpers, no arithmetic (tests/js/journal-scope.test.js).
import { hasMetric } from './dashboardMetrics.js';

export const isOwnShiftJournal = (data) => data?.scope === 'own_shift';

/** Rows of a shift report whose figure the API actually returned; null/empty rows are dropped, never shown as 0. */
export const visibleRows = (rows = []) => rows.filter((row) => hasMetric(row?.value));

/** Cashier name to print next to a shift, or null when the API withheld it. */
export const shiftCashierName = (shift) => {
    const name = shift?.user_name;
    return typeof name === 'string' && name.trim() !== '' ? name : null;
};

/** The z-report print route needs reports.view: offered only for the store scope. */
export const canPrintJournal = (data) => Boolean(data) && !isOwnShiftJournal(data);
