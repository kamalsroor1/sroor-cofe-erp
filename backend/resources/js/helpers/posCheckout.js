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
 * Returns { valid: false } when a partial amount is out of range (0 < paid < net).
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
        return { valid, paidAmount: paid, payments: hasSplit ? split : undefined };
    }

    if (hasSplit) {
        return { valid: true, paidAmount: normalize(net), payments: split };
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
