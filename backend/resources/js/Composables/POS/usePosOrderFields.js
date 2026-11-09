import { computed } from 'vue';

/**
 * Two-way proxies onto the active POS order, so the rest of the POS composables can
 * treat cart / customer / payment fields as plain refs.
 */
export function usePosOrderFields(activeOrder) {
    const field = (key, read) =>
        computed({
            get: () => read(activeOrder.value?.[key]),
            set: (val) => {
                if (activeOrder.value) activeOrder.value[key] = val;
            },
        });

    return {
        cart: field('cart', (v) => v || []),
        selectedCustomerId: field('selectedCustomerId', (v) => v ?? null),
        selectedCustomer: field('selectedCustomer', (v) => v ?? null),
        activePriceTier: field('activePriceTier', (v) => v || 'retail'),
        discountType: field('discountType', (v) => v || 'percentage'),
        discountValue: field('discountValue', (v) => v ?? '0'),
        paymentType: field('paymentType', (v) => v || 'cash'),
        paymentMethod: field('paymentMethod', (v) => v || 'cash'),
        paidAmount: field('paidAmount', (v) => v ?? '0.000'),
        cashReceived: field('cashReceived', (v) => v ?? '0.000'),
        additionalExpenses: field('additionalExpenses', (v) => v || []),
    };
}
