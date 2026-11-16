import test from 'node:test';
import assert from 'node:assert/strict';
import {
    fromDatetimeLocal,
    toDatetimeLocal,
    vatInclusiveText,
    buildPlanForm,
} from '../../resources/js/helpers/planEditor.js';

test('datetime-local round-trips the same instant with the browser offset', () => {
    const iso = '2026-12-01T02:00:00+02:00';
    const local = toDatetimeLocal(iso);
    assert.match(local, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
    const back = fromDatetimeLocal(local);
    assert.match(back, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00[+-]\d{2}:\d{2}$/);
    assert.equal(Date.parse(back), Date.parse(iso));
});

test('empty or invalid datetime values map to empty / null', () => {
    assert.equal(toDatetimeLocal(null), '');
    assert.equal(toDatetimeLocal('nope'), '');
    assert.equal(fromDatetimeLocal(''), null);
    assert.equal(fromDatetimeLocal('nope'), null);
});

test('VAT hint follows the API rate and disappears at zero', () => {
    assert.equal(vatInclusiveText('100', '14.000'), '114.00');
    assert.equal(vatInclusiveText('100', '0.000'), '');
    assert.equal(vatInclusiveText('100', undefined), '');
    assert.equal(vatInclusiveText('abc', '14.000'), '');
});

test('a new plan is not public by default', () => {
    assert.equal(buildPlanForm(null, [], []).is_public, false);
});
