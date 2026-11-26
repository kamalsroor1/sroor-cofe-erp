// #232 §7: arrow-key navigation of radiogroups (segmented control, plan cards, method buttons).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextRadioIndex } from '../../resources/js/helpers/radioNav.js';

test('LTR: right/down go forward, left/up go back, wrapping around', () => {
    assert.equal(nextRadioIndex('ArrowRight', 0, 3), 1);
    assert.equal(nextRadioIndex('ArrowDown', 2, 3), 0);
    assert.equal(nextRadioIndex('ArrowLeft', 0, 3), 2);
    assert.equal(nextRadioIndex('ArrowUp', 1, 3), 0);
});

test('RTL mirrors only the horizontal arrows', () => {
    assert.equal(nextRadioIndex('ArrowLeft', 0, 3, true), 1);
    assert.equal(nextRadioIndex('ArrowRight', 0, 3, true), 2);
    assert.equal(nextRadioIndex('ArrowDown', 0, 3, true), 1);
});

test('other keys and empty groups do nothing', () => {
    assert.equal(nextRadioIndex('Enter', 0, 3), null);
    assert.equal(nextRadioIndex('ArrowRight', 0, 0), null);
});
