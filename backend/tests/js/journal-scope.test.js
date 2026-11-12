// ROLE-01: null-safe daily journal / shift figures. Run: node --test tests/js/journal-scope.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    isOwnShiftJournal,
    visibleRows,
    shiftCashierName,
    canPrintJournal,
} from '../../resources/js/helpers/journalScope.js';
import { drawerDifference } from '../../resources/js/helpers/shiftCash.js';

test('scope detection and print gate', () => {
    assert.equal(isOwnShiftJournal({ scope: 'own_shift' }), true);
    assert.equal(isOwnShiftJournal({ scope: 'store' }), false);
    assert.equal(isOwnShiftJournal(undefined), false);
    assert.equal(canPrintJournal({ scope: 'store' }), true);
    assert.equal(canPrintJournal({ scope: 'own_shift' }), false);
    assert.equal(canPrintJournal(null), false);
});

test('visibleRows drops null/empty but keeps real zeros', () => {
    const rows = [
        { value: null },
        { value: undefined },
        { value: '' },
        { value: '0.000' },
        { value: 0 },
        { value: '5.000' },
    ];
    assert.equal(visibleRows(rows).length, 3);
    assert.deepEqual(visibleRows(), []);
});

test('shiftCashierName hides null and blank names', () => {
    assert.equal(shiftCashierName({ user_name: null }), null);
    assert.equal(shiftCashierName({ user_name: '  ' }), null);
    assert.equal(shiftCashierName(null), null);
    assert.equal(shiftCashierName({ user_name: 'Ali' }), 'Ali');
});

test('drawerDifference gives no preview when expected cash is withheld', () => {
    assert.equal(drawerDifference('10.000', null), null);
    assert.equal(drawerDifference('10.000', undefined), null);
    assert.equal(drawerDifference('', '5.000'), null);
    assert.deepEqual(drawerDifference('10.000', '0.000'), { amount: '10.000', sign: 1 });
});
