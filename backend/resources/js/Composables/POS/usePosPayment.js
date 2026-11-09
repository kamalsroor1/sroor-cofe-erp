import { ref, computed, watch } from 'vue';
import { normalize, dSum } from '../../helpers/decimal';
import { usePosCheckout } from '../usePosCheckout';

/**
 * Totals, discount, expenses and split-payment state for the active order, plus the watchers
 * that keep paid/received amounts in step with the net (incl. the POS-02 walk-in rule).
 */
export function usePosPayment({ fields, activeOrder, activeOrderId, isWalkInCustomer }) {
    const {
        cart,
        discountType,
        discountValue,
        paymentType,
        paymentMethod,
        paidAmount,
        cashReceived,
        additionalExpenses,
    } = fields;

    const showExpensesModal = ref(false);
    const showMultiPaymentModal = ref(false);
    const multiPayments = ref([]);

    // Exact (scale 3) checkout math lives in usePosCheckout; the numbers below are display previews only.
    const { checkoutNet, changePreview, buildPayment, buildPayloadLines } = usePosCheckout({
        cart,
        discountType,
        discountValue,
        additionalExpenses,
        paymentType,
        paymentMethod,
        cashReceived,
        multiPayments,
    });

    const cartSubtotal = computed(() =>
        cart.value.reduce((sum, item) => sum + parseFloat(item.quantity) * parseFloat(item.unit_price), 0)
    );
    const cartTotalQuantity = computed(() => cart.value.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0));
    const discountAmount = computed(() => {
        const val = parseFloat(discountValue.value) || 0;
        if (val <= 0) return 0;
        if (discountType.value === 'percentage') return (cartSubtotal.value * val) / 100;
        return Math.min(val, cartSubtotal.value);
    });
    const customerExpensesTotal = computed(() =>
        additionalExpenses.value
            .filter((exp) => (exp.paid_by || 'customer_account') === 'customer_account')
            .reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0)
    );
    const cartNetTotal = computed(() => Number(checkoutNet.value));
    const changeDue = computed(() => Number(changePreview.value));

    watch(isWalkInCustomer, (walkIn) => {
        if (!walkIn) return;
        if (paymentType.value !== 'cash') paymentType.value = 'cash';
        multiPayments.value = [];
        paidAmount.value = normalize(checkoutNet.value);
        cashReceived.value = normalize(checkoutNet.value);
    });

    watch(checkoutNet, (newNet) => {
        // A split built for the previous net must never be sent; the server rejects mismatches.
        multiPayments.value = [];
        if (paymentType.value === 'cash') {
            paidAmount.value = normalize(newNet);
            cashReceived.value = normalize(newNet);
        } else if (paymentType.value === 'credit') {
            paidAmount.value = '0.000';
            cashReceived.value = '0.000';
        }
    });

    // A split belongs to the payment type it was built for.
    watch(paymentType, () => {
        multiPayments.value = [];
    });

    // Idempotency key lives per checkout attempt: any change to what is being sold starts a new one.
    const checkoutFingerprint = computed(() =>
        JSON.stringify([
            cart.value.map((i) => [i.id, i.quantity, i.unit_price]),
            discountType.value,
            discountValue.value,
            paymentType.value,
            paymentMethod.value,
            multiPayments.value,
            additionalExpenses.value,
        ])
    );

    watch([activeOrderId, checkoutFingerprint], ([orderId, fingerprint], [prevOrderId, prevFingerprint]) => {
        if (orderId !== prevOrderId || fingerprint === prevFingerprint) return;
        if (activeOrder.value?.checkoutUuid) activeOrder.value.checkoutUuid = null;
    });

    const applyDiscountPreset = ({ value, type }) => {
        discountValue.value = value.toString();
        discountType.value = type;
    };

    const openMultiPayment = () => {
        // A credit order has no payments; switch to cash visibly instead of splitting behind the cashier's back.
        if (paymentType.value === 'credit') paymentType.value = 'cash';
        showMultiPaymentModal.value = true;
    };

    // The payment type stays what the cashier chose; the modal already validated the split for it.
    const confirmMultiPayment = (payments) => {
        const lines = payments.map((p) => ({ method: p.method, amount: normalize(p.amount) }));
        const totalPaid = dSum(lines.map((p) => p.amount));
        multiPayments.value = lines;
        showMultiPaymentModal.value = false;
        paidAmount.value = paymentType.value === 'partial' ? totalPaid : checkoutNet.value;
        cashReceived.value = totalPaid;
    };

    const applyExpenses = (val) => {
        additionalExpenses.value = val;
        showExpensesModal.value = false;
    };

    return {
        showExpensesModal,
        showMultiPaymentModal,
        multiPayments,
        checkoutNet,
        buildPayment,
        buildPayloadLines,
        cartSubtotal,
        cartTotalQuantity,
        discountAmount,
        customerExpensesTotal,
        cartNetTotal,
        changeDue,
        applyDiscountPreset,
        openMultiPayment,
        confirmMultiPayment,
        applyExpenses,
    };
}
