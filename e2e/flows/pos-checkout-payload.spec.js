// SP-6: POS checkout payload builder (split-payment contract, CTO option A).
// The client must send exact scale-3 strings, never flip payment_type, and let
// the server compute the change.
//
// Node-only: imports the helper directly, no page and no server data.
import { test, expect } from '@playwright/test';
import {
    computeCheckoutNet,
    previewChange,
    buildCheckoutPayment,
} from '../../backend/resources/js/helpers/posCheckout.js';

const kiloCart = [{ quantity: 0.25, unit_price: 550.5 }]; // 137.625

test.describe('POS checkout payload (exact, server-authoritative)', () => {
    test('fractional kilo net keeps 3 decimals like the server', () => {
        expect(computeCheckoutNet({ cart: kiloCart })).toBe('137.625');
        expect(computeCheckoutNet({ cart: [{ quantity: '2.000', unit_price: '550.000' }] })).toBe('1100.000');
    });

    test('float drift in quantities never leaks into the net', () => {
        // 0.1 + 0.2 kg stored as a float sum.
        expect(computeCheckoutNet({ cart: [{ quantity: 0.1 + 0.2, unit_price: '100' }] })).toBe('30.000');
    });

    test('percentage discount and customer expenses mirror the server', () => {
        const net = computeCheckoutNet({
            cart: kiloCart,
            discountType: 'percentage',
            discountValue: '10',
            expenses: [
                { amount: '15', paid_by: 'customer_account' },
                { amount: '40', paid_by: 'treasury_cash' },
            ],
        });
        // 137.625 - bcdiv(bcmul(137.625, 10, 4), 100, 3) = 137.625 - 13.762 = 123.863; + 15
        expect(net).toBe('138.863');
    });

    test('fixed discount is clamped to the subtotal', () => {
        expect(computeCheckoutNet({ cart: kiloCart, discountType: 'fixed', discountValue: '500' })).toBe('0.000');
    });

    test('cash without split and exact cash sends no payments', () => {
        const res = buildCheckoutPayment({
            paymentType: 'cash',
            paymentMethod: 'cash',
            cashReceived: '137.625',
            net: '137.625',
        });
        expect(res).toEqual({ valid: true, paidAmount: '137.625', payments: undefined });
    });

    test('cash overpay without split sends the tendered amount so the server computes change', () => {
        const res = buildCheckoutPayment({
            paymentType: 'cash',
            paymentMethod: 'cash',
            cashReceived: '200',
            net: '137.625',
        });
        expect(res.paidAmount).toBe('137.625');
        expect(res.payments).toEqual([{ method: 'cash', amount: '200.000' }]);
    });

    test('card overpay without split is sent as-is so the server rejects it (422)', () => {
        const res = buildCheckoutPayment({
            paymentType: 'cash',
            paymentMethod: 'visa',
            cashReceived: '200',
            net: '137.625',
        });
        expect(res.payments).toEqual([{ method: 'visa', amount: '200.000' }]);
    });

    test('split cash overpay keeps the lines exact and the change preview positive', () => {
        const split = [
            { method: 'visa', amount: 100 },
            { method: 'cash', amount: '50' },
        ];
        const res = buildCheckoutPayment({
            paymentType: 'cash',
            paymentMethod: 'cash',
            cashReceived: '150',
            net: '137.625',
            splitPayments: split,
        });
        expect(res).toEqual({
            valid: true,
            paidAmount: '137.625',
            payments: [
                { method: 'visa', amount: '100.000' },
                { method: 'cash', amount: '50.000' },
            ],
        });
        expect(previewChange({ paymentType: 'cash', cashReceived: '0', net: '137.625', splitPayments: split })).toBe(
            '12.375'
        );
    });

    test('partial split below net stays partial and pays the split total', () => {
        const res = buildCheckoutPayment({
            paymentType: 'partial',
            paymentMethod: 'cash',
            cashReceived: '0',
            net: '137.625',
            splitPayments: [{ method: 'instapay', amount: '37.625' }],
        });
        expect(res).toEqual({
            valid: true,
            paidAmount: '37.625',
            payments: [{ method: 'instapay', amount: '37.625' }],
        });
    });

    test('partial out of range is invalid (never silently switched to cash)', () => {
        for (const cashReceived of ['0', '137.625', '500']) {
            const res = buildCheckoutPayment({
                paymentType: 'partial',
                paymentMethod: 'cash',
                cashReceived,
                net: '137.625',
            });
            expect(res.valid).toBe(false);
        }
    });

    test('credit sends no payments and zero paid', () => {
        const res = buildCheckoutPayment({
            paymentType: 'credit',
            paymentMethod: 'cash',
            cashReceived: '500',
            net: '137.625',
        });
        expect(res).toEqual({ valid: true, paidAmount: '0.000', payments: undefined });
        expect(previewChange({ paymentType: 'credit', cashReceived: '500', net: '137.625' })).toBe('0.000');
    });
});
