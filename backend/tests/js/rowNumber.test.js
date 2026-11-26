// #222: the "#" column must never render NaN.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { rowNumber } from '../../resources/js/helpers/rowNumber.js';

test('first page numbers from 1', () => {
    assert.equal(rowNumber(0, { current_page: 1, per_page: 20 }), 1);
    assert.equal(rowNumber(2, { current_page: 1, per_page: 20 }), 3);
});

test('later pages offset by per_page', () => {
    assert.equal(rowNumber(0, { current_page: 3, per_page: 20 }), 41);
});

test('missing or partial pagination falls back to the list index', () => {
    assert.equal(rowNumber(4), 5);
    assert.equal(rowNumber(4, null), 5);
    assert.equal(rowNumber(4, {}), 5);
    assert.equal(rowNumber(1, { current_page: 2 }), 2);
    assert.equal(rowNumber(1, { per_page: 20 }), 2);
    assert.equal(rowNumber(1, { current_page: 'x', per_page: undefined }), 2);
});

test('undefined index never yields NaN', () => {
    assert.equal(rowNumber(undefined, { current_page: 1, per_page: 20 }), 1);
});
