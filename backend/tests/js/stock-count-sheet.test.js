// SHOP-02: stocktake counting screen helpers (barcode match, scan increment, differences, filters).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    filterLines,
    findLineByCode,
    isCounted,
    lineDifference,
    nextScanQuantity,
} from '../../resources/js/helpers/stockCountSheet.js';

const lines = [
    {
        item_id: 1,
        item_code: 'BN-COL',
        item_name: 'بن كولومبي',
        system_qty: '10.000',
        counted_qty: null,
        difference: null,
    },
    { item_id: 2, item_code: 'TEA-01', item_name: 'شاي', system_qty: '5.000', counted_qty: '5.000', difference: null },
    { item_id: 3, item_code: 'SUG-1', item_name: 'سكر', system_qty: '2.500', counted_qty: '3.000', difference: null },
];

test('a scanned barcode matches the item code, trimmed and case-insensitive', () => {
    assert.equal(findLineByCode(lines, '  bn-col ')?.item_id, 1);
    assert.equal(findLineByCode(lines, 'UNKNOWN'), null);
    assert.equal(findLineByCode(lines, ''), null);
});

test('each scan adds exactly one, starting from zero', () => {
    assert.equal(nextScanQuantity(null), '1.000');
    assert.equal(nextScanQuantity(''), '1.000');
    assert.equal(nextScanQuantity('2.500'), '3.500');
});

test('difference = counted - snapshot while open; the posted difference once closed', () => {
    assert.equal(lineDifference(lines[2], '3.000', true), '0.500');
    assert.equal(lineDifference(lines[0], '7', true), '-3.000');
    assert.equal(lineDifference(lines[0], null, true), null);
    // Blind count: no snapshot, no difference.
    assert.equal(lineDifference({ ...lines[0], system_qty: null }, '4', true), null);
    // Posted: the server difference (computed against the live balance) wins.
    assert.equal(lineDifference({ ...lines[0], difference: '-1.000' }, '7', false), '-1.000');
});

test('filters: uncounted, counted, differences and search', () => {
    const countedOf = (l) => l.counted_qty;
    const differenceOf = (l) => lineDifference(l, l.counted_qty, true);
    const ids = (filter, search = '') =>
        filterLines(lines, { filter, search, countedOf, differenceOf }).map((l) => l.item_id);

    assert.deepEqual(ids('all'), [1, 2, 3]);
    assert.deepEqual(ids('uncounted'), [1]);
    assert.deepEqual(ids('counted'), [2, 3]);
    assert.deepEqual(ids('differences'), [3]);
    assert.deepEqual(ids('all', 'tea'), [2]);
    assert.deepEqual(ids('all', 'سكر'), [3]);
});

test('isCounted treats null, undefined and empty as not counted (zero is counted)', () => {
    assert.equal(isCounted(null), false);
    assert.equal(isCounted(undefined), false);
    assert.equal(isCounted(''), false);
    assert.equal(isCounted('0.000'), true);
});
