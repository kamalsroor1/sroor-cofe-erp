import { computed } from 'vue';
import { normalize } from '../helpers/decimal';
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
        items: cart.value.map((line) => ({
            item_id: line.id,
            quantity: normalize(line.quantity),
            unit_price: normalize(line.unit_price),
        })),
        additional_expenses: additionalExpenses.value.map((exp) => ({
            title: exp.title,
            amount: normalize(exp.amount),
            paid_by: exp.paid_by || 'customer_account',
        })),
    });

    return { checkoutNet, changePreview, buildPayment, buildPayloadLines };
}
