// Z-report print component: lines, order and the optional closed_at row (source-level check, no DOM runner in this repo).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { URL } from 'node:url';

const src = readFileSync(
    new URL('../../resources/js/Components/DailyJournal/ShiftZReportPrint.vue', import.meta.url),
    'utf8'
);

test('lines appear in the documented order', () => {
    const keys = [
        'opening_cash_balance',
        'total_cash_sales',
        'total_payments_collected',
        'total_expenses',
        'total_supplier_paid',
        'total_refunds',
        'expected_cash_balance',
        'actual_cash_balance',
        'cash_difference',
    ];
    const idx = keys.map((k) => src.indexOf(`r.${k}`));
    assert.ok(idx.every((i) => i > -1));
    assert.deepEqual(
        [...idx].sort((a, b) => a - b),
        idx
    );
});

test('closed_at row is conditional and the sheet is print-only', () => {
    assert.match(src, /r\.closed_at \? formatDateTime/);
    assert.match(src, /hidden print:block/);
});
