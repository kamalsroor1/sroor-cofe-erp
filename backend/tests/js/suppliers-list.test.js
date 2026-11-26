// Trello #228: supplier cards read `summary` (not `metrics`) and the debt filter is sent as `debt_status`.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    EMPTY_SUPPLIER_METRICS,
    buildSuppliersParams,
    supplierMetricsFrom,
} from '../../resources/js/helpers/suppliersList.js';

test('cards are read from the summary the API returns', () => {
    const body = {
        success: true,
        data: [{ id: 1, current_balance: '2000.000' }],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
        summary: { total_payable: '2000.000', creditors_count: 1, total_suppliers: 1 },
    };
    assert.deepEqual(supplierMetricsFrom(body), {
        total_payable: '2000.000',
        creditors_count: 1,
        total_suppliers: 1,
    });
});

test('a response without summary (or nothing at all) falls back to zeros', () => {
    assert.deepEqual(supplierMetricsFrom({ data: [] }), { ...EMPTY_SUPPLIER_METRICS });
    assert.deepEqual(supplierMetricsFrom(undefined), { ...EMPTY_SUPPLIER_METRICS });
    assert.deepEqual(
        supplierMetricsFrom({ summary: { total_payable: '0.000', creditors_count: 0, total_suppliers: 0 } }),
        {
            total_payable: '0.000',
            creditors_count: 0,
            total_suppliers: 0,
        }
    );
});

test('the debt filter is sent as debt_status and omitted for "all"', () => {
    assert.deepEqual(buildSuppliersParams({ search: 'nile', debtStatus: 'creditor', page: 2 }), {
        search: 'nile',
        debt_status: 'creditor',
        page: 2,
        per_page: 15,
    });
    assert.deepEqual(buildSuppliersParams({ debtStatus: 'all' }), {
        search: undefined,
        debt_status: undefined,
        page: 1,
        per_page: 15,
    });
});
