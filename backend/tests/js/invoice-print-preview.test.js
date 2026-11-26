// Card #238 (demo trial 2026-11-26): the in-app A4 and 80mm previews printed the raw
// payment method ("cash") and the A4 terms in 10px slate-700. Guards both templates.
// Run: node --test tests/js/invoice-print-preview.test.js   (part of `npm run test:js`)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { URL } from 'node:url';

const read = (rel) => readFileSync(new URL(`../../resources/js/Components/Invoices/Show/${rel}`, import.meta.url), 'utf8');

const a4 = read('InvoiceShowA4Document.vue');
const thermal = read('InvoiceShowThermalReceipt.vue');

test('A4 and thermal previews translate the payment method through the shared helper', () => {
    for (const source of [a4, thermal]) {
        assert.match(source, /invoicePaymentLabels\(invoice, t\)/);
        assert.doesNotMatch(source, /\{\{\s*invoice\??\.payment_method\s*\}\}/);
    }
});

test('A4 terms and notes are at least 12px with strong contrast', () => {
    const terms = a4.slice(a4.indexOf("invoices.terms_and_conditions"), a4.indexOf("invoices.terms_3"));
    assert.doesNotMatch(terms, /text-\[(8|9|10|11)px\]/);
    assert.doesNotMatch(terms, /text-slate-(400|500|600|700)/);
});
