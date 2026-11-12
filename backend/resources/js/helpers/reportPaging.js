// PERF-01: the item, inventory and treasury report tabs load their own paged endpoints. Pure
// helpers (no Vue, no HTTP) so the param building and response shapes are unit-testable.

export const REPORT_PAGE_SIZE = 25;

const DEFAULT_META = Object.freeze({ current_page: 1, per_page: REPORT_PAGE_SIZE, total: 0, last_page: 1 });

export const normalizeReportMeta = (meta, rowCount = 0) => ({
    ...DEFAULT_META,
    total: rowCount,
    ...(meta ?? {}),
});

/** Query params shared by every /reports/* request. `stock_filter` is the inventory tab only. */
export const buildReportParams = (filters, { withStock = false, page = 1, perPage = REPORT_PAGE_SIZE } = {}) => ({
    period: filters.period,
    from: filters.from || undefined,
    to: filters.to || undefined,
    store_id: filters.store_id !== 'all' ? filters.store_id : undefined,
    stock_filter: withStock && filters.stock_filter !== 'all' ? filters.stock_filter : undefined,
    page,
    per_page: perPage,
});

/** GET /reports/items -> { data, totals, meta } */
export const normalizeItemsResponse = (body) => {
    const rows = Array.isArray(body?.data) ? body.data : [];
    return { rows, totals: body?.totals ?? null, extra: null, meta: normalizeReportMeta(body?.meta, rows.length) };
};

/** GET /reports/inventory -> data: { items, meta, ...valuation } */
export const normalizeInventoryResponse = (body) => {
    const data = body?.data ?? {};
    const rows = Array.isArray(data.items) ? data.items : [];
    return { rows, totals: null, extra: data, meta: normalizeReportMeta(data.meta, rows.length) };
};

/** GET /reports/treasury -> data: { ledger_entries, ledger_total, meta, ...totals } */
export const normalizeTreasuryResponse = (body) => {
    const data = body?.data ?? {};
    const rows = Array.isArray(data.ledger_entries) ? data.ledger_entries : [];
    const meta = normalizeReportMeta(data.meta, rows.length);
    if (data.ledger_total !== undefined && !data.meta) meta.total = Number(data.ledger_total);
    return { rows, totals: null, extra: data, meta };
};

/** Total to show in a badge: the API's `meta.total`, never the length of a capped list. */
export const reportTotal = (loadedMeta, loaded, fallbackTotal = 0) =>
    loaded ? Number(loadedMeta?.total) || 0 : Number(fallbackTotal) || 0;
