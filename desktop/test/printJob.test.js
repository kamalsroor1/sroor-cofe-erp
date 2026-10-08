'use strict';

// APP-5: renderer print payloads are validated and the receipt shell is built from
// whitelisted values only (paper width cannot inject CSS).

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const { normalizePrintJob, buildReceiptDocument, MAX_PRINT_HTML_LENGTH, MAX_PRINT_COPIES } = require(
    path.join(__dirname, '..', 'src', 'hardware', 'printJob.js')
);

const defaults = { thermalPrinterName: 'XP-80', paperWidth: '58mm' };

test('rejects non-object and oversized payloads', () => {
    assert.equal(normalizePrintJob(null, defaults), null);
    assert.equal(normalizePrintJob([], defaults), null);
    assert.equal(normalizePrintJob({ html: 5 }, defaults), null);
    assert.equal(normalizePrintJob({ html: 'x'.repeat(MAX_PRINT_HTML_LENGTH + 1) }, defaults), null);
});

test('fills printer and paper width from settings', () => {
    assert.deepEqual(normalizePrintJob({ html: '<p>1</p>' }, defaults), {
        html: '<p>1</p>',
        printerName: 'XP-80',
        paperWidth: '58mm',
        copies: 1,
    });
});

test('clamps copies and whitelists paper width', () => {
    const job = normalizePrintJob({ html: 'a', copies: 99, paperWidth: '80mm;} body{display:none' }, {});
    assert.equal(job.copies, MAX_PRINT_COPIES);
    assert.equal(job.paperWidth, '80mm');
    assert.equal(normalizePrintJob({ html: 'a', copies: -3 }, {}).copies, 1);
    assert.equal(normalizePrintJob({ html: 'a', copies: 'abc' }, {}).copies, 1);
    assert.equal(normalizePrintJob({ html: 'a', paperWidth: '58mm' }, {}).paperWidth, '58mm');
});

test('ignores invalid printer names from the renderer', () => {
    assert.equal(normalizePrintJob({ html: 'a', printerName: 'a\nb' }, defaults).printerName, 'XP-80');
    assert.equal(normalizePrintJob({ html: 'a', printerName: {} }, {}).printerName, '');
    assert.equal(normalizePrintJob({ html: 'a', printerName: 'POS-58' }, defaults).printerName, 'POS-58');
});

test('receipt document sizes the page from the whitelisted width', () => {
    const doc58 = buildReceiptDocument('<b>x</b>', '58mm');
    assert.match(doc58, /size: 58mm auto/);
    assert.match(doc58, /<b>x<\/b>/);
    assert.match(buildReceiptDocument('', 'weird'), /size: 80mm auto/);
});
