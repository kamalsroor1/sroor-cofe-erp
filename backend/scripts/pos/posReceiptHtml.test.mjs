// SEC-07: the thermal receipt escapes every interpolated value.
// Run from backend/: node --test scripts/pos/posReceiptHtml.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { escapeHtml } from '../../resources/js/helpers/escapeHtml.js';
import { buildThermalHtml } from '../../resources/js/helpers/posReceiptHtml.js';

test('escapeHtml escapes the five HTML metacharacters and tolerates null', () => {
    assert.equal(escapeHtml(`<b a="1" c='2'>&</b>`), '&lt;b a=&quot;1&quot; c=&#39;2&#39;&gt;&amp;&lt;/b&gt;');
    assert.equal(escapeHtml(null), '');
    assert.equal(escapeHtml(undefined), '');
    assert.equal(escapeHtml(12.5), '12.5');
});

test('thermal receipt never emits raw markup from item, company or invoice values', () => {
    const payload = '<img src=x onerror=alert(1)>';
    const html = buildThermalHtml(
        {
            invoice_number: payload,
            invoice_date: '2026-01-01',
            net_total: '10',
            items: [{ item_name: payload, quantity: '1', total_price: '10' }],
        },
        {
            companyName: `<script>${payload}</script>`,
            currencyLabel: '<i>EGP</i>',
            t: (key, params) => (params ? `${key} ${params.number}` : key),
            formatDate: (d) => `<${d}>`,
            formatDateTime: () => '',
        }
    );

    assert.ok(!html.includes('<img'), 'item name must be escaped');
    assert.ok(!html.includes('<script>'), 'company name must be escaped');
    assert.ok(!html.includes('<i>'), 'currency label must be escaped');
    assert.ok(!html.includes('<2026-01-01>'), 'date must be escaped');
    assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'));
    assert.ok(html.includes('10.00 &lt;i&gt;EGP&lt;/i&gt;'));
});
