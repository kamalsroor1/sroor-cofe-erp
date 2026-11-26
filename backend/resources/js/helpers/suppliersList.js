// Supplier list request/response mapping (Trello #228). GET /suppliers returns the
// cards under `summary` and reads the debt filter from `debt_status`.

export const EMPTY_SUPPLIER_METRICS = Object.freeze({
    total_payable: '0.000',
    creditors_count: 0,
    total_suppliers: 0,
});

export function buildSuppliersParams({ search = '', debtStatus = 'all', page = 1, perPage = 15 } = {}) {
    return {
        search: search || undefined,
        debt_status: debtStatus && debtStatus !== 'all' ? debtStatus : undefined,
        page,
        per_page: perPage,
    };
}

export function supplierMetricsFrom(body) {
    const summary = body?.summary;
    if (!summary || typeof summary !== 'object') return { ...EMPTY_SUPPLIER_METRICS };

    return {
        total_payable: summary.total_payable ?? EMPTY_SUPPLIER_METRICS.total_payable,
        creditors_count: Number(summary.creditors_count) || 0,
        total_suppliers: Number(summary.total_suppliers) || 0,
    };
}
