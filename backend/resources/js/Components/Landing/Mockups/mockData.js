/*
 * Sample figures drawn inside the decorative app-screen mockups of the marketing pages
 * (design/landing/build_landing.py). They are illustrations, not customer data: names are
 * translation keys under landing.mock.products, numbers are literal sample values.
 */

/** POS product tiles: product key, unit price, thumbnail tint. */
export const MOCK_PRODUCTS = Object.freeze([
    { key: 'rice', price: '38.00', color: '#C98B4A' },
    { key: 'oil', price: '95.00', color: '#E4A853' },
    { key: 'detergent', price: '185.00', color: '#7FB4D9' },
    { key: 'juice', price: '45.00', color: '#E7A15A' },
    { key: 'charger', price: '250.00', color: '#8A8F98' },
    { key: 'earbuds', price: '450.00', color: '#5B6B8A' },
    { key: 'tshirt', price: '220.00', color: '#7DAE6B' },
    { key: 'cumin', price: '320.00', color: '#9B7A4A' },
    { key: 'shampoo', price: '120.00', color: '#B58BC9' },
]);

/** POS cart lines: weighted lines use the kg quantity label. */
export const MOCK_CART = Object.freeze([
    { key: 'charger', qty: '1', price: '250.00', total: '250.00', weighted: false },
    { key: 'detergent', qty: '1', price: '185.00', total: '185.00', weighted: false },
    { key: 'rice', qty: '3', price: '38.00', total: '114.00', weighted: false },
    { key: 'cumin', qty: '0.250', price: '320.00', total: '80.00', weighted: true },
]);
export const MOCK_CART_TOTAL = '629.00';

/** Inventory balances per location; `low` flags the «running low» chip. */
export const MOCK_STOCK = Object.freeze([
    { key: 'rice', main: '64', warehouse: '1,200', van: '80', low: false },
    { key: 'detergent', main: '18', warehouse: '240', van: '36', low: false },
    { key: 'charger', main: '3', warehouse: '40', van: '0', low: true },
    { key: 'cumin', main: '2.500', warehouse: '25.000', van: '0.000', low: true },
    { key: 'juice', main: '96', warehouse: '1,440', van: '120', low: false },
]);

export const MOCK_REPORT_KPIS = Object.freeze([
    { key: 'sales', value: '284,500', delta: 12, up: true },
    { key: 'expenses', value: '41,250', delta: 3, up: false },
    { key: 'net_profit', value: '68,930', delta: 9, up: true },
]);
export const MOCK_REPORT_MONTHS = Object.freeze([42, 51, 47, 58, 55, 63, 60, 71, 68, 74, 79, 86]);
export const MOCK_REPORT_YEAR = '2026';
export const MOCK_ITEM_MARGINS = Object.freeze([
    { key: 'cumin', margin: 41 },
    { key: 'charger', margin: 34 },
    { key: 'detergent', margin: 18 },
]);

export const MOCK_PHONE = Object.freeze({
    sales: '12,480.00',
    growth: 18,
    profit: '3,215.50',
    treasury: '8,940.00',
    week: [5, 7, 4, 8, 6, 9, 11],
    best: [
        { key: 'detergent', value: '3,700' },
        { key: 'rice', value: '2,280' },
        { key: 'charger', value: '1,750' },
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

export const MOCK_TRANSFER = Object.freeze({ key: 'cumin', qty: '25.000', van: 1 });
