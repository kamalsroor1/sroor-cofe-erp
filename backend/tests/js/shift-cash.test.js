// helpers/shiftCash.js: counted cash and who may close a shift; blind count in the close dialogs (DEC-SHIFT).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { URL } from 'node:url';
import {
    canCloseShift,
    drawerDifference,
    isShiftOpenedBy,
    isValidCountedCash,
} from '../../resources/js/helpers/shiftCash.js';

test('nothing counted yet gives no difference', () => {
    assert.equal(drawerDifference('', '100.000'), null);
    assert.equal(drawerDifference('   ', '100.000'), null);
    assert.equal(drawerDifference(null, '100.000'), null);
    assert.equal(drawerDifference(undefined, '100.000'), null);
});

test('counted equal to the expected string is balanced', () => {
    assert.deepEqual(drawerDifference('62.500', '62.500'), { amount: '0.000', sign: 0 });
    assert.deepEqual(drawerDifference('62.5', '62.500'), { amount: '0.000', sign: 0 });
    assert.deepEqual(drawerDifference('0', '0.000'), { amount: '0.000', sign: 0 });
});

test('surplus and shortage are exact, never string concatenation or float noise', () => {
    assert.deepEqual(drawerDifference('100.300', '100.100'), { amount: '0.200', sign: 1 });
    assert.deepEqual(drawerDifference('90', '100.250'), { amount: '-10.250', sign: -1 });
    assert.deepEqual(drawerDifference('0.3', '0.1'), { amount: '0.200', sign: 1 });
});

test('a withheld expected value (null, ROLE-01) gives no difference preview instead of a fake surplus', () => {
    assert.equal(drawerDifference('15.000', undefined), null);
    assert.equal(drawerDifference('15.000', null), null);
});

test('only the opener or a manager with the close permission may close a shift (DEC-SHIFT)', () => {
    assert.equal(canCloseShift({ canClose: true, isManager: false, isOwn: true }), true);
    assert.equal(canCloseShift({ canClose: true, isManager: true, isOwn: false }), true);
    assert.equal(canCloseShift({ canClose: true, isManager: false, isOwn: false }), false);
    // reports.view without the close permission does not close.
    assert.equal(canCloseShift({ canClose: false, isManager: true, isOwn: true }), false);
});

test('a shift is "own" only when its user_id is the user', () => {
    assert.equal(isShiftOpenedBy({ user_id: 7 }, 7), true);
    assert.equal(isShiftOpenedBy({ user_id: '7' }, 7), true);
    assert.equal(isShiftOpenedBy({ user_id: 8 }, 7), false);
    assert.equal(isShiftOpenedBy({ user_id: null }, 7), false);
    assert.equal(isShiftOpenedBy(null, 7), false);
    assert.equal(isShiftOpenedBy({ user_id: 7 }, null), false);
});

const read = (path) => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');

test('the counted cash must be typed, numeric and not negative', () => {
    assert.equal(isValidCountedCash(''), false);
    assert.equal(isValidCountedCash('  '), false);
    assert.equal(isValidCountedCash(null), false);
    assert.equal(isValidCountedCash('-5'), false);
    assert.equal(isValidCountedCash('abc'), false);
    assert.equal(isValidCountedCash('0'), true);
    assert.equal(isValidCountedCash('1974'), true);
    assert.equal(isValidCountedCash(62.5), true);
    assert.equal(isValidCountedCash('.5'), true);
});

test('the counted cash accepts at most 3 decimals', () => {
    assert.equal(isValidCountedCash('1.234'), true);
    assert.equal(isValidCountedCash('1.'), true);
    assert.equal(isValidCountedCash('.123'), true);
    assert.equal(isValidCountedCash('1.2345'), false);
    assert.equal(isValidCountedCash('.1234'), false);
    assert.equal(isValidCountedCash('0.0001'), false);
    assert.equal(isValidCountedCash(1.2345), false);
});

test('journal close modal starts the counted field empty and never reads the expected cash (blind count)', () => {
    const journal = read('Composables/useDailyJournal.js');
    const open = journal.slice(journal.indexOf('const openCloseShiftModal'), journal.indexOf('const submitCloseShift'));
    assert.match(open, /actual_cash_balance = ''/);
    assert.doesNotMatch(open, /expected_cash/);
    assert.match(open, /closeFigures\.load\(\)/);

    const figures = read('Composables/useShiftCloseFigures.js');
    assert.match(figures, /api\.get\('\/shifts\/current'\)/);
    assert.doesNotMatch(figures, /expected_cash_balance/);

    const view = read('views/DailyJournal/DailyJournalView.vue');
    assert.doesNotMatch(view, /expected-cash/);
    assert.match(view, /mayCloseShift/);
});

test('the close modal shows no expected cash and no difference before the close', () => {
    const modal = read('Components/DailyJournal/CloseShiftModal.vue');
    assert.match(modal, /isValidCountedCash\(props\.form\.actual_cash_balance\)/);
    assert.match(modal, /:disabled="!canSubmit"/);
    assert.match(modal, /treasury\.blind_count_hint/);
    assert.doesNotMatch(modal, /expected_cash|expectedCash|expected_balance/);
    assert.doesNotMatch(modal, /drawer_difference_label|diffText/);
});

test('POS close panel reads fresh figures, hides the expected cash and offers close only to opener or manager', () => {
    const pos = read('Composables/POS/usePosShift.js');
    assert.match(pos, /figures\.load\(\)/);
    assert.match(pos, /isValidCountedCash\(countedCash\.value\)/);
    assert.match(pos, /countedCash\.value = ''/);
    assert.match(pos, /canCloseActiveShift/);
    const panel = read('Components/POS/POSShiftPanel.vue');
    assert.match(panel, /!isValidCountedCash\(countedCash\)/);
    assert.match(panel, /v-if="canClose"/);
    assert.doesNotMatch(panel, /expectedCash|shift_report_expected/);
});

test('the shift-required gate has a back button', () => {
    assert.match(read('Components/POS/POSShiftGate.vue'), /pos-shift-gate-back/);
});
