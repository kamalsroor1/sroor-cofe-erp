'use strict';

// APP-5: when the configured/default printer fails, printing must still work:
// silent print -> system print dialog (pick another printer / "Microsoft Print to PDF")
// -> save as PDF. The drawer pulse path never opens a dialog.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const { resolveTargetPrinter, printWithFallback, isCancelled } = require(
    path.join(__dirname, '..', 'src', 'hardware', 'printFallback.js')
);

const PRINTERS = [
    { name: 'XP-80', displayName: 'XP-80' },
    { name: 'Microsoft Print to PDF', displayName: 'Microsoft Print to PDF' },
];

function fakeDeps(results = {}) {
    const calls = [];
    return {
        calls,
        printSilent: async (printerName) => {
            calls.push(['silent', printerName]);
            return results.silent || { success: true };
        },
        printWithDialog: async () => {
            calls.push(['dialog']);
            return results.dialog || { success: true };
        },
        saveAsPdf: async () => {
            calls.push(['pdf']);
            return results.pdf || { success: true, filePath: 'C:\\receipt.pdf' };
        },
    };
}

test('resolveTargetPrinter: no printers installed', () => {
    assert.deepEqual(resolveTargetPrinter([], 'XP-80'), { printerName: null, reason: 'no_printer' });
    assert.deepEqual(resolveTargetPrinter(null, ''), { printerName: null, reason: 'no_printer' });
});

test('resolveTargetPrinter: requested printer is matched by name or display name', () => {
    assert.deepEqual(resolveTargetPrinter(PRINTERS, 'XP-80'), { printerName: 'XP-80', reason: null });
    const shared = [{ name: '\\\\PC\\POS', displayName: 'POS on PC' }];
    assert.deepEqual(resolveTargetPrinter(shared, 'POS on PC'), { printerName: '\\\\PC\\POS', reason: null });
});

test('resolveTargetPrinter: a missing requested printer is reported, not silently swapped', () => {
    assert.deepEqual(resolveTargetPrinter(PRINTERS, 'Old-Printer'), {
        printerName: null,
        reason: 'printer_not_found',
    });
});

test('resolveTargetPrinter: no request means the system default (empty device name)', () => {
    assert.deepEqual(resolveTargetPrinter(PRINTERS, ''), { printerName: '', reason: null });
    assert.deepEqual(resolveTargetPrinter(PRINTERS, undefined), { printerName: '', reason: null });
});

test('isCancelled recognises Chromium cancel reasons', () => {
    assert.equal(isCancelled('Print job canceled'), true);
    assert.equal(isCancelled('cancelled'), true);
    assert.equal(isCancelled('Print job failed'), false);
    assert.equal(isCancelled(undefined), false);
});

test('silent success never opens a dialog', async () => {
    const deps = fakeDeps();
    const res = await printWithFallback({ printers: PRINTERS, requestedPrinter: 'XP-80' }, deps);
    assert.deepEqual(res, { success: true, method: 'silent', printerName: 'XP-80' });
    assert.deepEqual(deps.calls, [['silent', 'XP-80']]);
});

test('default printer failure falls back to the print dialog', async () => {
    const deps = fakeDeps({ silent: { success: false, error: 'Print job failed' } });
    const res = await printWithFallback({ printers: PRINTERS, requestedPrinter: '' }, deps);
    assert.deepEqual(res, { success: true, method: 'dialog', fallbackReason: 'print_failed' });
    assert.deepEqual(deps.calls, [['silent', ''], ['dialog']]);
});

test('a missing configured printer goes straight to the dialog', async () => {
    const deps = fakeDeps();
    const res = await printWithFallback({ printers: PRINTERS, requestedPrinter: 'Gone' }, deps);
    assert.deepEqual(res, { success: true, method: 'dialog', fallbackReason: 'printer_not_found' });
    assert.deepEqual(deps.calls, [['dialog']]);
});

test('cancelling the dialog stops without forcing a PDF', async () => {
    const deps = fakeDeps({
        silent: { success: false, error: 'Print job failed' },
        dialog: { success: false, error: 'Print job canceled' },
    });
    const res = await printWithFallback({ printers: PRINTERS, requestedPrinter: 'XP-80' }, deps);
    assert.deepEqual(res, { success: false, error: 'print_cancelled', fallbackReason: 'print_failed' });
    assert.deepEqual(deps.calls, [['silent', 'XP-80'], ['dialog']]);
});

test('a failing dialog falls back to saving a PDF', async () => {
    const deps = fakeDeps({
        silent: { success: false, error: 'Print job failed' },
        dialog: { success: false, error: 'Invalid printer settings' },
    });
    const res = await printWithFallback({ printers: PRINTERS, requestedPrinter: 'XP-80' }, deps);
    assert.deepEqual(res, {
        success: true,
        method: 'pdf',
        fallbackReason: 'print_failed',
        filePath: 'C:\\receipt.pdf',
    });
    assert.deepEqual(deps.calls, [['silent', 'XP-80'], ['dialog'], ['pdf']]);
});

test('no printers installed goes straight to PDF', async () => {
    const deps = fakeDeps();
    const res = await printWithFallback({ printers: [], requestedPrinter: '' }, deps);
    assert.equal(res.method, 'pdf');
    assert.equal(res.fallbackReason, 'no_printer');
    assert.deepEqual(deps.calls, [['pdf']]);
});

test('cancelled PDF save and failed PDF save are reported with codes', async () => {
    const cancelled = await printWithFallback(
        { printers: [], requestedPrinter: '' },
        fakeDeps({ pdf: { success: false, cancelled: true } })
    );
    assert.deepEqual(cancelled, { success: false, error: 'print_cancelled', fallbackReason: 'no_printer' });
    const failed = await printWithFallback(
        { printers: [], requestedPrinter: '' },
        fakeDeps({ pdf: { success: false, error: 'EACCES' } })
    );
    assert.deepEqual(failed, { success: false, error: 'pdf_failed', fallbackReason: 'no_printer' });
});

test('interactive fallback can be disabled (drawer pulse / background jobs)', async () => {
    const deps = fakeDeps({ silent: { success: false, error: 'Print job failed' } });
    const res = await printWithFallback(
        { printers: PRINTERS, requestedPrinter: 'XP-80', allowInteractive: false },
        deps
    );
    assert.deepEqual(res, { success: false, error: 'print_failed' });
    assert.deepEqual(deps.calls, [['silent', 'XP-80']]);
});

test('a throwing step is treated as a failure, not an unhandled rejection', async () => {
    const deps = fakeDeps();
    deps.printSilent = async () => {
        throw new Error('boom');
    };
    const res = await printWithFallback({ printers: PRINTERS, requestedPrinter: 'XP-80' }, deps);
    assert.equal(res.success, true);
    assert.equal(res.method, 'dialog');
    assert.equal(res.fallbackReason, 'print_failed');
});
