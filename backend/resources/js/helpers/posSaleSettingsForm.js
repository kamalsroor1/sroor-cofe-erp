// SET-01: form <-> API helpers of Settings > POS > sale settings. The settings API returns the lists as CSV
// strings (or nothing when the shop never saved them); the form edits them as arrays.

export const SALE_PAYMENT_METHODS = ['cash', 'instapay', 'e_wallet', 'visa', 'bank_transfer'];
export const SALE_DEFAULTS = Object.freeze({
    pos_enabled_payment_methods: ['cash', 'instapay', 'e_wallet', 'visa', 'bank_transfer'],
    pos_cash_denominations: ['50', '100', '200', '500'],
    pos_discount_presets: ['0', '5', '10'],
    pos_auto_print: 'none',
    pos_receipt_copies: 1,
});
export const MAX_DENOMINATIONS = 6;
export const MAX_PRESETS = 4;

export function parseCsv(raw, fallback) {
    if (raw === undefined || raw === null || String(raw).trim() === '') return [...fallback];
    return String(raw)
        .split(',')
        .map((v) => v.trim())
        .filter((v) => v !== '');
}

/** Form values from the GET /settings dictionary. */
export function saleSettingsFromApi(s) {
    const methods = parseCsv(s.pos_enabled_payment_methods, SALE_DEFAULTS.pos_enabled_payment_methods);
    if (!methods.includes('cash')) methods.unshift('cash');
    const copies = Number(s.pos_receipt_copies);
    return {
        pos_enabled_payment_methods: methods,
        pos_cash_denominations: parseCsv(s.pos_cash_denominations, SALE_DEFAULTS.pos_cash_denominations),
        pos_discount_presets: parseCsv(s.pos_discount_presets, SALE_DEFAULTS.pos_discount_presets),
        pos_auto_print: s.pos_auto_print === 'thermal' ? 'thermal' : 'none',
        pos_receipt_copies: Number.isInteger(copies) && copies >= 1 && copies <= 3 ? copies : 1,
    };
}

/** A positive amount (denominations) or a 0..100 percent (presets), at most 3 decimals; returns the clean string or null. */
export function cleanNumber(value, { min, max }) {
    const text = String(value ?? '')
        .trim()
        .replace(',', '.');
    if (!/^\d{1,9}(\.\d{1,3})?$/.test(text)) return null;
    const n = Number(text);
    if (n < min || (max !== undefined && n > max)) return null;
    if (min > 0 && n === 0) return null;
    return String(n);
}

/** Inline validation messages (translation keys) of the sale settings part of the form. */
export function validateSaleSettings(form) {
    const found = {};
    const dens = form.pos_cash_denominations || [];
    if (dens.length < 1 || dens.length > MAX_DENOMINATIONS)
        found.pos_cash_denominations = ['settings.pos_denominations_invalid'];
    const presets = form.pos_discount_presets || [];
    if (presets.length < 1 || presets.length > MAX_PRESETS)
        found.pos_discount_presets = ['settings.pos_presets_invalid'];
    return found;
}
