import { ref, computed } from 'vue';

// SET-01: defaults equal the behaviour before the setting existed, so a shop that never saved anything is unchanged.
export const DEFAULT_SALE_SETTINGS = Object.freeze({
    payment_methods: ['cash', 'instapay', 'e_wallet', 'visa', 'bank_transfer'],
    cash_denominations: ['50', '100', '200', '500'],
    discount_presets: ['0', '5', '10'],
    auto_print: 'none',
    receipt_copies: 1,
});

const saleSettings = ref({ ...DEFAULT_SALE_SETTINGS });

/** Pure: how many receipts to print for a finished sale (0 = none). `manual` = the cashier chose "save and print". */
export function receiptCopiesToPrint(settings, manual) {
    const auto = settings?.auto_print === 'thermal';
    if (!auto && !manual) return 0;
    const copies = Number(settings?.receipt_copies);
    return Number.isInteger(copies) && copies >= 1 && copies <= 3 ? copies : 1;
}

/** Pure: keep only the presets the active branch's discount cap allows. */
export function presetsWithinCap(presets, cap) {
    const list = (presets || []).map((p) => Number(p)).filter((n) => Number.isFinite(n) && n >= 0 && n <= 100);
    if (cap === null || cap === undefined || cap === '') return list;
    const max = Number(cap);
    return Number.isFinite(max) ? list.filter((n) => n <= max) : list;
}

/**
 * Pure: even layout of the quick-tender row (the denominations + the exact/clear button). Up to 5 buttons
 * share one row; more wrap into two rows of equal columns, and when the count is odd the last (exact)
 * button spans two columns so both rows end flush.
 */
export function tenderGridLayout(count) {
    const n = Math.max(1, Number.isInteger(count) ? count : 1);
    const cols = n <= 5 ? n : Math.ceil(n / 2);
    const lastSpan = n > cols && n % 2 === 1 ? 2 : 1;
    return { cols, lastSpan };
}

/** Shared by the catalog loader (writes from /pos/bootstrap) and the checkout components (read). */
export function usePosSaleSettings() {
    const applySaleSettings = (payload) => {
        saleSettings.value = { ...DEFAULT_SALE_SETTINGS, ...(payload && typeof payload === 'object' ? payload : {}) };
    };

    const enabledMethods = computed(() => saleSettings.value.payment_methods || DEFAULT_SALE_SETTINGS.payment_methods);
    const isMethodEnabled = (key) => enabledMethods.value.includes(key === 'smart_wallet' ? 'e_wallet' : key);
    const cashDenominations = computed(() =>
        (saleSettings.value.cash_denominations || DEFAULT_SALE_SETTINGS.cash_denominations).map((d) => Number(d))
    );

    return { saleSettings, applySaleSettings, enabledMethods, isMethodEnabled, cashDenominations };
}
