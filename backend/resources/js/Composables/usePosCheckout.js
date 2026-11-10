import { computed } from 'vue';
import { normalize, isPositive } from '../helpers/decimal';
import { DISCOUNT_FIXED } from '../helpers/posDiscount';
import { computeCheckoutNet, previewChange, buildCheckoutPayment } from '../helpers/posCheckout';

/**
 * Exact checkout state for the POS view. Takes the view's refs/computeds and
 * returns the exact net, the change preview, and the payload builder.
 */
export function usePosCheckout({
    cart,
    discountType,
    discountValue,
    additionalExpenses,
    paymentType,
    paymentMethod,
    cashReceived,
    multiPayments,
}) {
    const checkoutNet = computed(() =>
        computeCheckoutNet({
            cart: cart.value,
            discountType: discountType.value,
            discountValue: discountValue.value,
            expenses: additionalExpenses.value,
        })
    );

    const changePreview = computed(() =>
        previewChange({
            paymentType: paymentType.value,
            cashReceived: cashReceived.value,
            net: checkoutNet.value,
            splitPayments: multiPayments.value,
        })
    );

    const buildPayment = () =>
        buildCheckoutPayment({
            paymentType: paymentType.value,
            paymentMethod: paymentMethod.value,
            cashReceived: cashReceived.value,
            net: checkoutNet.value,
            splitPayments: multiPayments.value,
        });

    const buildPayloadLines = () => ({
        discount_value: normalize(discountValue.value),
        // POS-07: a line discount goes as a percent (the server turns it into money from the price it charges)
        // or as an amount.
        items: cart.value.map((line) => ({
            item_id: line.id,
            quantity: normalize(line.quantity),
            unit_price: normalize(line.unit_price),
            ...(Number.isInteger(line.entry_sequence) && line.entry_sequence >= 1
                ? { entry_sequence: line.entry_sequence }
                : {}),
            ...(isPositive(line.discount_value ?? '0')
                ? line.discount_type === DISCOUNT_FIXED
                    ? { discount_amount: normalize(line.discount_value) }
                    : { discount_percent: normalize(line.discount_value) }
                : {}),
        })),
        additional_expenses: additionalExpenses.value.map((exp) => ({
            title: exp.title,
            amount: normalize(exp.amount),
            paid_by: exp.paid_by || 'customer_account',
        })),
    });

    return { checkoutNet, changePreview, buildPayment, buildPayloadLines };
}
