'use strict';

// APP-5: drawer kick = ESC/POS pulse sent raw to the receipt printer; if the queue
// refuses RAW data, fall back to a tiny silent driver job (drivers with "open drawer
// after print" enabled). Never opens a print dialog.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const { createDrawerKicker } = require(path.join(__dirname, '..', 'src', 'hardware', 'drawerKick.js'));

const PRINTERS = [{ name: 'XP-80', displayName: 'XP-80' }];

function setup({ printers = PRINTERS, configured = '', raw = { success: true }, driver = { success: true } } = {}) {
    const calls = [];
    const kicker = createDrawerKicker({
        listPrinters: async () => printers,
        getConfiguredPrinter: () => configured,
        sendRaw: async (name, bytes) => {
            calls.push(['raw', name, [...bytes]]);
            return raw;
        },
        printDriverPulse: async (name) => {
            calls.push(['driver', name]);
            return driver;
        },
    });
    return { kicker, calls };
}

test('sends ESC p 0 25 250 raw to the requested printer', async () => {
    const { kicker, calls } = setup();
    const res = await kicker.kick('XP-80');
    assert.deepEqual(res, { success: true, method: 'escpos', printerName: 'XP-80' });
    assert.deepEqual(calls, [['raw', 'XP-80', [0x1b, 0x70, 0x00, 0x19, 0xfa]]]);
});

test('uses the configured printer when the renderer passes none', async () => {
    const { kicker, calls } = setup({ configured: 'XP-80' });
    const res = await kicker.kick('');
    assert.equal(res.printerName, 'XP-80');
    assert.equal(calls[0][1], 'XP-80');
});

test('falls back to the driver pulse when the queue refuses RAW data', async () => {
    const { kicker, calls } = setup({ raw: { success: false, error: 'raw_print_failed' } });
    const res = await kicker.kick('XP-80');
    assert.deepEqual(res, { success: true, method: 'driver', printerName: 'XP-80' });
    assert.deepEqual(
        calls.map((c) => c[0]),
        ['raw', 'driver']
    );
});

test('both paths failing returns drawer_failed', async () => {
    const { kicker } = setup({
        raw: { success: false, error: 'raw_print_failed' },
        driver: { success: false, error: 'print_failed' },
    });
    assert.deepEqual(await kicker.kick('XP-80'), { success: false, error: 'drawer_failed' });
});

test('an unknown printer is never replaced by another device', async () => {
    const { kicker, calls } = setup();
    assert.deepEqual(await kicker.kick('Old-Printer'), { success: false, error: 'printer_not_found' });
    assert.deepEqual(calls, []);
});

test('no printers installed returns no_printer', async () => {
    const { kicker, calls } = setup({ printers: [] });
    assert.deepEqual(await kicker.kick('XP-80'), { success: false, error: 'no_printer' });
    assert.deepEqual(calls, []);
});

test('no printer chosen anywhere uses only the driver pulse on the system default', async () => {
    const { kicker, calls } = setup();
    const res = await kicker.kick(undefined);
    assert.deepEqual(res, { success: true, method: 'driver', printerName: '' });
    assert.deepEqual(calls, [['driver', '']]);
});

test('non-string printer arguments are ignored', async () => {
    const { kicker, calls } = setup({ configured: 'XP-80' });
    await kicker.kick({ evil: true });
    assert.equal(calls[0][1], 'XP-80');
});

test('a throwing dependency resolves to drawer_failed', async () => {
    const kicker = createDrawerKicker({
        listPrinters: async () => {
            throw new Error('boom');
        },
        getConfiguredPrinter: () => '',
        sendRaw: async () => ({ success: true }),
        printDriverPulse: async () => ({ success: true }),
    });
    assert.deepEqual(await kicker.kick('XP-80'), { success: false, error: 'drawer_failed' });
});
