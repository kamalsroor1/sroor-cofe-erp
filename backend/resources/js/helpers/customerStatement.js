// PERF-01: the customer statement is paged by the API. Pure helpers (no Vue, no HTTP) so the page
// state, the opening-balance row and the "fetch every page for print" loop are unit-testable.

export const STATEMENT_PAGE_SIZE = 100;
export const PRINT_PAGE_SIZE = 200;
export const PRINT_MAX_ROWS = 5000;

const DEFAULT_META = Object.freeze({ current_page: 1, per_page: STATEMENT_PAGE_SIZE, total: 0, last_page: 1 });

// Dates are Y-m-d strings, so a string comparison is a date comparison.
export const rangeIsReversed = (from, to) => Boolean(from && to && String(from) > String(to));

/**
 * Query params for GET /customers/{id}/statement. No dates at all means the whole history, so
 * `period=all` is sent explicitly (the API's default without it is the last 90 days).
 */
export const buildStatementParams = ({ from = '', to = '', page = 1, perPage = STATEMENT_PAGE_SIZE } = {}) => {
    const params = { page, per_page: perPage };
    if (from) params.from_date = from;
    if (to) params.to_date = to;
    if (!from && !to) params.period = 'all';
    return params;
};

export const normalizeStatement = (data) => {
    const ledger = Array.isArray(data?.ledger) ? data.ledger : [];
    return {
        customer: data?.customer ?? null,
        ledger,
        summary: data?.summary ?? {},
        filters: data?.filters ?? { from_date: null, to_date: null, all_history: true },
        meta: { ...DEFAULT_META, total: ledger.length, ...(data?.meta ?? {}) },
    };
};

/** The period starts mid-history: there is a balance carried forward from before it. */
export const hasOpeningRow = (filters) => Boolean(filters?.from_date) && !filters?.all_history;

/**
 * Rows for the table: the opening-balance row (flagged `_opening`, not a transaction) first when
 * the period does not start at the beginning, only on page 1. The balances are the API's.
 */
export const buildStatementRows = ({ ledger = [], summary = {}, filters = {}, page = 1, openingLabel = '' }) => {
    if (page !== 1 || !hasOpeningRow(filters)) return ledger;
    const opening = {
        _opening: true,
        date: filters.from_date,
        type: openingLabel,
        ref_number: '',
        debit: null,
        credit: null,
        balance_after: summary.opening_balance ?? '0.000',
        notes: '',
    };
    return [opening, ...ledger];
};

/** Translation key + params describing the period of the statement (screen header and print). */
export const describePeriod = (filters) => {
    const from = filters?.from_date || '';
    const to = filters?.to_date || '';
    if (from && to) return { key: 'contacts.statement_period_range', params: { from, to } };
    if (from) return { key: 'contacts.statement_period_from', params: { from } };
    if (to) return { key: 'contacts.statement_period_until', params: { to } };
    return { key: 'contacts.statement_period_all', params: {} };
};

/**
 * Loads every page of the statement, sequentially. `fetchPage(page, perPage)` resolves to a
 * normalized statement. Stops before the first extra request when the period is bigger than
 * `maxRows` (status `too_large`) and between pages when `isCancelled()` turns true.
 */
export const collectAllPages = async ({
    fetchPage,
    perPage = PRINT_PAGE_SIZE,
    maxRows = PRINT_MAX_ROWS,
    onProgress = () => {},
    isCancelled = () => false,
}) => {
    const first = await fetchPage(1, perPage);
    const total = Number(first.meta.total) || first.ledger.length;
    if (total > maxRows) return { status: 'too_large', total };

    const rows = [...first.ledger];
    onProgress(rows.length, total);

    const lastPage = Number(first.meta.last_page) || 1;
    for (let page = 2; page <= lastPage; page += 1) {
        if (isCancelled()) return { status: 'cancelled', total };
        const next = await fetchPage(page, perPage);
        rows.push(...next.ledger);
        onProgress(rows.length, total);
    }
    if (isCancelled()) return { status: 'cancelled', total };

    return { status: 'ok', total, first, rows };
};
