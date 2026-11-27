// SHOP-01: POS safety - receipt escaping and exact number formatting, cash-drawer rule,
// stock check on add, and exact (non-float) totals.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildThermalHtml, saleIncludesCash } from '../../resources/js/helpers/posReceiptHtml.js';
import { stockCheck, STOCK_OK, STOCK_WARN, STOCK_BLOCK } from '../../resources/js/helpers/posStock.js';
import { dSum } from '../../resources/js/helpers/decimal.js';
import { lineGross } from '../../resources/js/helpers/posDiscount.js';

const options = (extra = {}) => ({
    companyName: 'Shop',
    currencyLabel: 'EGP',
    currencyDecimals: 2,
    t: (key, params) => (params?.number ? `${key} ${params.number}` : key),
    formatDate: (d) => String(d),
    formatDateTime: () => 'now',
    ...extra,
});

test('receipt escapes a <script> item name and every user-controlled text', () => {
    const html = buildThermalHtml(
        {
            invoice_number: '<b>INV-1</b>',
            invoice_date: '2026-11-15',
            net_total: '10.000',
            items: [{ item_name: '<script>alert(1)</script>', quantity: '1.000', total_price: '10.000' }],
        },
        options({ companyName: '"Evil" & <Co>', currencyLabel: '<i>EGP</i>' })
    );

    assert.ok(!html.includes('<script>'), 'raw <script> must never reach the printer HTML');
    assert.ok(html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
    assert.ok(html.includes('&quot;Evil&quot; &amp; &lt;Co&gt;'));
    assert.ok(html.includes('&lt;b&gt;INV-1&lt;/b&gt;'));
    assert.ok(html.includes('&lt;i&gt;EGP&lt;/i&gt;'));
});

test('receipt escapes <img src=x onerror=alert(1)> payloads in item name, company name, and invoice number', () => {
    const xss = '<img src=x onerror=alert(1)>';
    const html = buildThermalHtml(
        {
            invoice_number: xss,
            invoice_date: '2026-11-27',
            net_total: '15.000',
            items: [{ item_name: xss, quantity: '1.000', total_price: '15.000' }],
        },
        options({ companyName: xss })
    );

    assert.ok(!html.includes(xss), 'raw <img src=x onerror=alert(1)> must never appear in receipt HTML');
    assert.ok(!html.includes('onerror=alert(1)>'), 'raw onerror handler must not be emitted unescaped');
    assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'));
});

test('receipt formats amounts exactly with half-up rounding, never through floats', () => {
    const html = buildThermalHtml(
        {
            invoice_date: '2026-11-15',
            net_total: '1234567.005',
            items: [
                { item_name: 'Beans', quantity: '1.250', total_price: '1.005' },
                { item_name: 'Cup', quantity: '3.000', total_price: '0.300' },
            ],
        },
        options()
    );

    assert.ok(html.includes('1,234,567.01'), 'net total rounded half-up and grouped');
    assert.ok(html.includes('>1.01<'), '1.005 -> 1.01 (float toFixed(2) gives 1.00)');
    assert.ok(html.includes('>1.250<'), 'fractional quantity keeps its 3 decimals');
    assert.ok(html.includes('>3<'), 'whole quantity has no fraction');
    assert.ok(html.includes('>0.30<'));
});

test('receipt honours a 3-decimal currency', () => {
    const html = buildThermalHtml({ net_total: '1.0005', items: [] }, options({ currencyDecimals: 3 }));
    assert.ok(html.includes('1.001'));
});

test('cash drawer opens only when the sale took cash', () => {
    assert.equal(saleIncludesCash({ payment_type: 'cash', payment_method: 'cash', paid_amount: '10.000' }), true);
    assert.equal(saleIncludesCash({ payment_type: 'cash', payment_method: 'visa', paid_amount: '10.000' }), false);
    assert.equal(saleIncludesCash({ payment_type: 'credit', payment_method: 'cash', paid_amount: '0.000' }), false);
    assert.equal(saleIncludesCash({ payment_type: 'partial', payment_method: 'cash', paid_amount: '0.000' }), false);
    assert.equal(
        saleIncludesCash({
            payment_type: 'cash',
            payment_method: 'visa',
            payments: [
                { payment_method: 'visa', amount: '5.000' },
                { payment_method: 'cash', amount: '5.000' },
            ],
        }),
        true,
        'a split sale with a cash line opens the drawer'
    );
    assert.equal(
        saleIncludesCash({
            payment_type: 'cash',
            payment_method: 'cash',
            payments: [{ payment_method: 'instapay', amount: '10.000' }],
        }),
        false,
        'saved payments win over the header method'
    );
    assert.equal(saleIncludesCash(null), false);
});

test('stock check: enough stock is fine, short stock warns or blocks by the setting', () => {
    assert.equal(stockCheck({ currentStock: '5.000', inCart: '2', adding: '3', allowNegative: false }), STOCK_OK);
    assert.equal(stockCheck({ currentStock: '5.000', inCart: '5', adding: '1', allowNegative: false }), STOCK_BLOCK);
    assert.equal(stockCheck({ currentStock: '0.000', adding: '1', allowNegative: false }), STOCK_BLOCK);
    assert.equal(stockCheck({ currentStock: '0.000', adding: '1', allowNegative: true }), STOCK_WARN);
    assert.equal(
        stockCheck({ currentStock: '0.000', adding: '1', allowNegative: null }),
        STOCK_WARN,
        'unknown never blocks'
    );
    assert.equal(stockCheck({ currentStock: '0.300', inCart: '0.1', adding: '0.2', allowNegative: false }), STOCK_OK);
    assert.equal(stockCheck({ currentStock: undefined, adding: '9' }), STOCK_OK, 'no stock figure, no check');
});

test('POS totals are exact: 0.1 + 0.2 -> 0.300', () => {
    assert.equal(dSum(['0.1', '0.2']), '0.300');
    assert.equal(dSum([0.1, 0.2]), '0.300');
    const cart = [
        { quantity: '1', unit_price: '0.1' },
        { quantity: '1', unit_price: '0.2' },
    ];
    assert.equal(dSum(cart.map(lineGross)), '0.300');
});

test('drawer: change handed back opens the drawer even when the cash line was trimmed away', () => {
    const inv = {
        payment_type: 'cash',
        payment_method: 'card',
        change_amount: '20.000',
        payments: [{ payment_method: 'card', amount: '100.000' }],
    };
    assert.equal(saleIncludesCash(inv), true);
    assert.equal(saleIncludesCash({ ...inv, change_amount: '0.000' }), false);
});
