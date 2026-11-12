// PERF-01: report tabs load their own paged endpoints; badges use meta.total, never a capped length.
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    buildReportParams,
    normalizeInventoryResponse,
    normalizeItemsResponse,
    normalizeTreasuryResponse,
    reportTotal,
} from '../../resources/js/helpers/reportPaging.js';

const filters = {
    period: 'this_year',
    from: '2026-01-01',
    to: '2026-11-12',
    store_id: 'all',
    stock_filter: 'in_stock',
};

test('params carry page/per_page; stock_filter only for the inventory tab; store only when chosen', () => {
    const items = buildReportParams(filters, { page: 3 });
    assert.equal(items.page, 3);
    assert.equal(items.per_page, 25);
    assert.equal(items.stock_filter, undefined);
    assert.equal(items.store_id, undefined);
    assert.equal(buildReportParams(filters, { withStock: true }).stock_filter, 'in_stock');
    assert.equal(buildReportParams({ ...filters, store_id: 4 }).store_id, 4);
});

test('items response: rows, API totals and meta.total (not the page length)', () => {
    const n = normalizeItemsResponse({
        data: [{ item_id: 1 }, { item_id: 2 }],
        totals: { total_revenue: '90.000' },
        meta: { total: 5000, current_page: 1, last_page: 200, per_page: 25 },
    });
    assert.equal(n.rows.length, 2);
    assert.equal(n.totals.total_revenue, '90.000');
    assert.equal(n.meta.total, 5000);
});

test('inventory and treasury responses map their own list keys', () => {
    const inv = normalizeInventoryResponse({
        data: { items: [{ id: 1 }], meta: { total: 4990 }, stock_cost_valuation: '1.000' },
    });
    assert.equal(inv.rows.length, 1);
    assert.equal(inv.meta.total, 4990);
    const tr = normalizeTreasuryResponse({
        data: { ledger_entries: [{}, {}, {}], ledger_total: 40123, meta: { total: 40123 } },
    });
    assert.equal(tr.rows.length, 3);
    assert.equal(tr.meta.total, 40123);
    assert.equal(normalizeTreasuryResponse({ data: { ledger_entries: [{}], ledger_total: 77 } }).meta.total, 77);
});

test('badge total: loaded meta wins, otherwise the comprehensive meta total', () => {
    assert.equal(reportTotal({ total: 4321 }, true, 100), 4321);
    assert.equal(reportTotal({ total: 0 }, false, 4321), 4321);
    assert.equal(reportTotal({}, false, undefined), 0);
});
