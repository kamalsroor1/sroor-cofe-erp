'use strict';

// APP-5: the cash drawer is opened by a real ESC/POS "generate pulse" command
// (ESC p m t1 t2) sent raw to the receipt printer, not by printing an HTML page.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const { buildDrawerKick } = require(path.join(__dirname, '..', 'src', 'hardware', 'escpos.js'));

test('default drawer kick is ESC p 0 25 250 (pin 2, 50 ms on, 500 ms off)', () => {
    const bytes = buildDrawerKick();
    assert.ok(Buffer.isBuffer(bytes));
    assert.deepEqual([...bytes], [0x1b, 0x70, 0x00, 0x19, 0xfa]);
});

test('pin 5 selects connector m = 1', () => {
    assert.equal(buildDrawerKick({ pin: 5 })[2], 0x01);
    assert.equal(buildDrawerKick({ pin: 2 })[2], 0x00);
});

test('unknown pins fall back to pin 2', () => {
    assert.equal(buildDrawerKick({ pin: 9 })[2], 0x00);
    assert.equal(buildDrawerKick({ pin: 'x' })[2], 0x00);
});

test('pulse timings are clamped to one byte in 2 ms units', () => {
    const fast = buildDrawerKick({ onMs: 0, offMs: -10 });
    assert.equal(fast[3], 1);
    assert.equal(fast[4], 1);
    const slow = buildDrawerKick({ onMs: 99999, offMs: 99999 });
    assert.equal(slow[3], 255);
    assert.equal(slow[4], 255);
    const custom = buildDrawerKick({ onMs: 100, offMs: 200 });
    assert.equal(custom[3], 50);
    assert.equal(custom[4], 100);
});
