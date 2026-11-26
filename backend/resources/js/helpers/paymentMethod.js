// #219: one place that turns an invoice's payment channel (payment_method) and settlement
// (payment_type) into translation keys. payment_type is cash / credit / partial (paid now vs on
// account); payment_method is the channel (cash / instapay / e_wallet / visa / bank_transfer…).
// An InstaPay sale has payment_type "cash", so labelling it by payment_type showed it as cash.

const METHOD_KEYS = {
    cash: 'invoices.payment_cash',
    instapay: 'invoices.payment_instapay',
    e_wallet: 'invoices.payment_wallet',
    visa: 'invoices.payment_card',
    bank_transfer: 'invoices.payment_bank',
    check: 'treasury.payment_methods_short.check',
    other: 'treasury.payment_methods_short.other',
};

// Older screens used short aliases for the same channels.
const ALIASES = { wallet: 'e_wallet', card: 'visa', bank: 'bank_transfer' };

export function normalizePaymentMethod(method) {
    if (!method) return 'cash';
    const value = ALIASES[method] ?? method;
    return METHOD_KEYS[value] ? value : 'other';
}

export function paymentMethodKey(method) {
    return METHOD_KEYS[normalizePaymentMethod(method)];
}

/**
 * Keys to display for an invoice: the channel, plus the settlement when it is not a full payment.
 * A credit invoice collected nothing now, so it has no channel to show.
 *
 * @returns {{ methodKey: string|null, typeKey: string|null }}
 */
export function invoicePaymentKeys(invoice) {
    const type = invoice?.payment_type;
    if (type === 'credit') return { methodKey: null, typeKey: 'invoices.credit' };

    return {
        methodKey: paymentMethodKey(invoice?.payment_method),
        typeKey: type === 'partial' ? 'invoices.partial' : null,
    };
}

/** The translated labels from invoicePaymentKeys(), channel first. */
export function invoicePaymentLabels(invoice, translate) {
    const { methodKey, typeKey } = invoicePaymentKeys(invoice);
    return [methodKey, typeKey].filter(Boolean).map((key) => translate(key));
}
