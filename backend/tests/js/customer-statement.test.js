// PERF-01: paged customer statement helpers (period=all, opening row, print collects every page).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    buildStatementParams,
    buildStatementRows,
    collectAllPages,
    describePeriod,
    hasOpeningRow,
    normalizeStatement,
    rangeIsReversed,
} from '../../resources/js/helpers/customerStatement.js';

const row = (n, balance) => ({ date: '2026-01-01', type: 'invoice', ref_number: `INV-${n}`, balance_after: balance });

test('the "all" preset (no dates) sends period=all, a dated range sends the dates only', () => {
    assert.deepEqual(buildStatementParams({ from: '', to: '' }), { page: 1, per_page: 100, period: 'all' });
    assert.deepEqual(buildStatementParams({ from: '2026-01-01', to: '2026-01-31', page: 2 }), {
        page: 2,
        per_page: 100,
        from_date: '2026-01-01',
        to_date: '2026-01-31',
    });
    assert.equal(buildStatementParams({ from: '2026-01-01' }).period, undefined);
});

test('a reversed range is detected client-side', () => {
    assert.equal(rangeIsReversed('2026-02-01', '2026-01-01'), true);
    assert.equal(rangeIsReversed('2026-01-01', '2026-01-01'), false);
    assert.equal(rangeIsReversed('', '2026-01-01'), false);
});

test('opening row: first on page 1 of a dated period, with the API opening balance', () => {
    const filters = { from_date: '2026-08-15', to_date: '2026-11-12', all_history: false };
    const summary = { opening_balance: '1250.500' };
    const rows = buildStatementRows({
        ledger: [row(1, '1300.000')],
        summary,
        filters,
        page: 1,
        openingLabel: 'Opening',
    });
    assert.equal(rows.length, 2);
    assert.equal(rows[0]._opening, true);
    assert.equal(rows[0].balance_after, '1250.500');
    assert.equal(rows[0].date, '2026-08-15');
    assert.equal(rows[1].ref_number, 'INV-1');
});

test('no opening row on page 2 or for the whole history', () => {
    const ledger = [row(101, '5.000')];
    const dated = { from_date: '2026-08-15', all_history: false };
    assert.equal(buildStatementRows({ ledger, summary: {}, filters: dated, page: 2 }).length, 1);
    const all = { from_date: null, to_date: null, all_history: true };
    assert.equal(buildStatementRows({ ledger, summary: {}, filters: all, page: 1 }).length, 1);
    assert.equal(hasOpeningRow(all), false);
});

test('normalizeStatement keeps the API meta and falls back safely', () => {
    const n = normalizeStatement({
        ledger: [row(1, '1.000')],
        meta: { current_page: 2, total: 250, last_page: 3, per_page: 100 },
    });
    assert.equal(n.meta.total, 250);
    assert.equal(n.meta.current_page, 2);
    const empty = normalizeStatement(undefined);
    assert.deepEqual(empty.ledger, []);
    assert.equal(empty.meta.total, 0);
});

test('describePeriod picks the translation key by which bounds exist', () => {
    assert.equal(describePeriod({ from_date: 'a', to_date: 'b' }).key, 'contacts.statement_period_range');
    assert.equal(describePeriod({ from_date: 'a' }).key, 'contacts.statement_period_from');
    assert.equal(describePeriod({ to_date: 'b' }).key, 'contacts.statement_period_until');
    assert.equal(describePeriod({}).key, 'contacts.statement_period_all');
});

const fakeApi = (total, pageSize) => {
    const calls = [];
    const fetchPage = async (page, perPage) => {
        calls.push({ page, perPage });
        const start = (page - 1) * pageSize;
        const count = Math.max(0, Math.min(pageSize, total - start));
        return {
            ledger: Array.from({ length: count }, (_, i) => row(start + i + 1, String(start + i + 1))),
            summary: { closing_balance: String(total) },
            filters: {},
            meta: { total, last_page: Math.max(1, Math.ceil(total / pageSize)), current_page: page },
        };
    };
    return { calls, fetchPage };
};

test('print collects every page sequentially and the running balance continues', async () => {
    const { calls, fetchPage } = fakeApi(450, 200);
    const progress = [];
    const result = await collectAllPages({ fetchPage, onProgress: (l, t) => progress.push([l, t]) });
    assert.equal(result.status, 'ok');
    assert.equal(result.rows.length, 450);
    assert.deepEqual(
        calls.map((c) => c.page),
        [1, 2, 3]
    );
    assert.equal(result.rows[449].balance_after, '450');
    assert.deepEqual(progress.at(-1), [450, 450]);
});

test('print refuses a huge statement after the first request', async () => {
    const { calls, fetchPage } = fakeApi(9000, 200);
    const result = await collectAllPages({ fetchPage, maxRows: 5000 });
    assert.equal(result.status, 'too_large');
    assert.equal(result.total, 9000);
    assert.equal(calls.length, 1);
});

test('print can be cancelled between pages', async () => {
    const { calls, fetchPage } = fakeApi(900, 200);
    let cancelled = false;
    const result = await collectAllPages({
        fetchPage,
        onProgress: (loaded) => {
            if (loaded >= 400) cancelled = true;
        },
        isCancelled: () => cancelled,
    });
    assert.equal(result.status, 'cancelled');
    assert.equal(calls.length, 2);
});
