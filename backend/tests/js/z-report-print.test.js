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
        'total_customer_receipts',
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

test('provisional (X) report swaps the title, shows the note and hides the null closing rows', () => {
    assert.match(src, /r\.is_provisional|report\?\.is_provisional/);
    assert.match(src, /treasury\.x_report_title/);
    assert.match(src, /treasury\.z_report_title/);
    assert.match(src, /treasury\.x_report_provisional_note/);
    assert.match(src, /isProvisional\.value && \['actual', 'difference'\]\.includes\(line\.key\)/);
    assert.match(src, /isProvisional\.value && r\.generated_at \? formatDateTime\(r\.generated_at\)/);
    assert.match(src, /v-if="isProvisional"/);
});

test('null/undefined money lines are omitted, never printed as 0', () => {
    assert.match(src, /line\.value !== null && line\.value !== undefined/);
    assert.doesNotMatch(src, /\?\? 0/);
    assert.match(src, /report\.total_credit_sales !== null/);
});

test('customer receipts row reuses an existing key', () => {
    assert.match(src, /treasury\.customer_collections/);
});
