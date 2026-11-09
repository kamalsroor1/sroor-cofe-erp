/**
 * POS checkout math (split-payment contract, CTO option A), exact at scale 3.
 *
 * Mirrors InvoiceService on the server so the client sends the same net the
 * server will compute. Everything here is a preview: the server stays the
 * authority for the paid amount, the change and every 422.
 */
import { normalize, dAdd, dSub, dMul, dPercent, dCmp, dMin, dMax0, dSum, isPositive } from './decimal.js';

const CUSTOMER_PAID_BY = 'customer_account';

/** Net total exactly as the server computes it: sum(qty x price) - discount + customer expenses. */
export function computeCheckoutNet({ cart = [], discountType = 'percentage', discountValue = '0', expenses = [] }) {
    const subtotal = dSum(cart.map((line) => dMul(line.quantity, line.unit_price)));

    let discount = '0.000';
    if (isPositive(discountValue)) {
        discount = discountType === 'percentage' ? dPercent(subtotal, discountValue) : normalize(discountValue);
    }
    discount = dMin(discount, subtotal);

    const customerExpenses = dSum(
        expenses.filter((exp) => (exp.paid_by || CUSTOMER_PAID_BY) === CUSTOMER_PAID_BY).map((exp) => dMax0(exp.amount))
    );

    return dAdd(dSub(subtotal, discount), customerExpenses);
}

const ARABIC_INDIC_ZERO = 0x0660;
const EXTENDED_ARABIC_INDIC_ZERO = 0x06f0;

/**
 * Turn what a cashier typed into a plain decimal string: Arabic-Indic digits (٠-٩, ۰-۹) and the
 * Arabic decimal separator become Latin; thousands separators (comma, U+066C, U+060C) are stripped, so "1,500" is 1500.
 */
export function sanitizeAmountInput(raw) {
    const latin = String(raw ?? '')
        .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - ARABIC_INDIC_ZERO))
        .replace(/[\u06f0-\u06f9]/g, (d) => String(d.charCodeAt(0) - EXTENDED_ARABIC_INDIC_ZERO))
        .replace(/\u066b/g, '.')
        .replace(/[\u066c\u060c,]/g, '')
        .replace(/[^0-9.]/g, '');
    const [whole, ...rest] = latin.split('.');
    return rest.length > 0 ? `${whole}.${rest.join('')}` : whole;
}

/** Normalize split lines to { method, amount } with '0.000' strings. */
export function normalizeSplit(payments = []) {
    return payments.map((p) => ({ method: p.method, amount: normalize(p.amount) }));
}

/** Live change preview shown while the cashier types; never sent to the server. */
export function previewChange({ paymentType, cashReceived, net, splitPayments = [] }) {
    if (paymentType !== 'cash') return '0.000';
    const received = splitPayments.length > 0 ? dSum(splitPayments.map((p) => p.amount)) : normalize(cashReceived);
    return dSub(received, net);
}

/**
 * Build paid_amount and payments[] for the checkout payload.
 * Returns { valid: false, reason } when a partial amount is out of range (0 < paid < net) or a cash tender is below the net.
 * payment_type is never changed here: the caller sends exactly what the cashier chose.
 */
export function buildCheckoutPayment({ paymentType, paymentMethod, cashReceived, net, splitPayments = [] }) {
    const split = normalizeSplit(splitPayments);
    const hasSplit = split.length > 0;

    if (paymentType === 'credit') {
        return { valid: true, paidAmount: '0.000', payments: undefined };
    }

    if (paymentType === 'partial') {
        const paid = hasSplit ? dSum(split.map((p) => p.amount)) : normalize(cashReceived);
        const valid = isPositive(paid) && dCmp(paid, net) < 0;
        return { valid, reason: 'partial_invalid', paidAmount: paid, payments: hasSplit ? split : undefined };
    }

    if (hasSplit) {
        return { valid: true, paidAmount: normalize(net), payments: split };
    }

    // A cash tender below the net would silently book the full net as paid: make the cashier fix it.
    if (isPositive(net) && dCmp(cashReceived, net) < 0) {
        return { valid: false, reason: 'tender_short', paidAmount: normalize(cashReceived), payments: undefined };
    }

    // Overpay without a split: send the tendered amount so the server computes the
    // change itself (and rejects a non-cash overpay with the same 422 as a split).
    if (dCmp(cashReceived, net) > 0) {
        return {
            valid: true,
            paidAmount: normalize(net),
            payments: [{ method: paymentMethod, amount: normalize(cashReceived) }],
        };
    }

    return { valid: true, paidAmount: normalize(net), payments: undefined };
}
