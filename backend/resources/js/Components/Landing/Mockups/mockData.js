/*
 * Sample figures drawn inside the decorative app-screen mockups of the marketing pages
 * (design/landing/build_landing.py). They are illustrations, not customer data: names are
 * translation keys under landing.mock.products, numbers are literal sample values.
 */

/** POS product tiles: product key, unit price, thumbnail tint. */
export const MOCK_PRODUCTS = Object.freeze([
    { key: 'brazilian', price: '320.00', color: '#C98B4A' },
    { key: 'yemeni', price: '540.00', color: '#8A5A33' },
    { key: 'espresso_blend', price: '380.00', color: '#5B3A24' },
    { key: 'cappuccino', price: '55.00', color: '#D9B38C' },
    { key: 'latte', price: '60.00', color: '#E7CBA9' },
    { key: 'green_tea', price: '35.00', color: '#7DAE6B' },
    { key: 'croissant', price: '40.00', color: '#E4A853' },
    { key: 'cardamom', price: '95.00', color: '#9BB06A' },
    { key: 'water', price: '10.00', color: '#7FB4D9' },
]);

/** POS cart lines: weighted lines use the kg quantity label. */
export const MOCK_CART = Object.freeze([
    { key: 'espresso_blend', qty: '2', price: '380.00', total: '760.00', weighted: true },
    { key: 'yemeni', qty: '0.750', price: '540.00', total: '405.00', weighted: true },
    { key: 'cappuccino', qty: '2', price: '55.00', total: '110.00', weighted: false },
    { key: 'croissant', qty: '1', price: '40.00', total: '40.00', weighted: false },
]);
export const MOCK_CART_TOTAL = '1,315.00';

/** Inventory balances per location; `low` flags the «running low» chip. */
export const MOCK_STOCK = Object.freeze([
    { key: 'brazilian', main: '18.500', warehouse: '120.000', van: '6.000', low: false },
    { key: 'yemeni', main: '4.250', warehouse: '35.000', van: '0.000', low: true },
    { key: 'espresso_blend', main: '22.000', warehouse: '80.500', van: '12.000', low: false },
    { key: 'cardamom', main: '1.200', warehouse: '9.000', van: '0.500', low: true },
    { key: 'paper_cups', main: '640', warehouse: '4,000', van: '300', low: false },
]);

export const MOCK_REPORT_KPIS = Object.freeze([
    { key: 'sales', value: '284,500', delta: 12, up: true },
    { key: 'expenses', value: '41,250', delta: 3, up: false },
    { key: 'net_profit', value: '68,930', delta: 9, up: true },
]);
export const MOCK_REPORT_MONTHS = Object.freeze([42, 51, 47, 58, 55, 63, 60, 71, 68, 74, 79, 86]);
export const MOCK_REPORT_YEAR = '2026';
export const MOCK_ITEM_MARGINS = Object.freeze([
    { key: 'espresso_blend', margin: 31 },
    { key: 'yemeni', margin: 27 },
    { key: 'cappuccino', margin: 58 },
]);

export const MOCK_PHONE = Object.freeze({
    sales: '12,480.00',
    growth: 18,
    profit: '3,215.50',
    treasury: '8,940.00',
    week: [5, 7, 4, 8, 6, 9, 11],
    best: [
        { key: 'espresso_blend', value: '4,560' },
        { key: 'yemeni', value: '2,700' },
        { key: 'cappuccino', value: '1,320' },
    ],
});

export const MOCK_BLEND = Object.freeze({
    weight: '1',
    parts: [
        { key: 'brazilian', share: 50, color: '#C98B4A' },
        { key: 'colombian', share: 30, color: '#8A5A33' },
        { key: 'yemeni', share: 20, color: '#5B3A24' },
    ],
    roasts: ['roast_light', 'roast_medium', 'roast_dark'],
    roast: 'roast_medium',
    grinds: ['grind_espresso', 'grind_french', 'grind_turkish'],
    grind: 'grind_espresso',
    cost: '286.40',
    price: '380.00',
});

export const MOCK_TRANSFER = Object.freeze({ key: 'brazilian', qty: '24.000', van: 1 });
