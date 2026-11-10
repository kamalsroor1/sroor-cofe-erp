import { ref, computed, watch } from 'vue';
import { normalize, dSum, dCmp } from '../../helpers/decimal';
import { usePosCheckout } from '../usePosCheckout';
import { summarizeDiscounts } from '../../helpers/posDiscount';

/**
 * Totals, discount, expenses and split-payment state for the active order, plus the watchers
 * that keep paid/received amounts in step with the net (incl. the POS-02 walk-in rule).
 */
export function usePosPayment({ fields, activeOrder, activeOrderId, isWalkInCustomer, maxDiscountPercent }) {
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

    // POS-07: exact discount totals (lines + invoice) and the cap warning. Display numbers only.
    const discountSummary = computed(() =>
        summarizeDiscounts({
            cart: cart.value,
            discountType: discountType.value,
            discountValue: discountValue.value,
            maxPercent: maxDiscountPercent?.value ?? null,
        })
    );
    const cartSubtotal = computed(() => Number(discountSummary.value.gross));
    const cartTotalQuantity = computed(() => cart.value.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0));
    const discountAmount = computed(() => Number(discountSummary.value.totalDiscount));
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

    watch(checkoutNet, (newNet, oldNet) => {
        // A split built for the previous net must never be sent; the server rejects mismatches.
        multiPayments.value = [];
        if (paymentType.value === 'cash') {
            paidAmount.value = normalize(newNet);
            // Only follow the net while the tender was still the auto-filled exact amount;
            // what the cashier typed (or tapped) is never overwritten by a later scan.
            if (dCmp(cashReceived.value, oldNet) === 0) cashReceived.value = normalize(newNet);
        } else if (paymentType.value === 'credit') {
            paidAmount.value = '0.000';
            cashReceived.value = '0.000';
        }
    });

    // Switching method to a non-cash one cannot give change: the tender is the exact net.
    watch(paymentMethod, (method) => {
        if (paymentType.value !== 'cash' || method === 'cash') return;
        cashReceived.value = normalize(checkoutNet.value);
    });

    // A split belongs to the payment type it was built for; the tender restarts for the new type.
    let seenOrderId = activeOrderId.value;
    watch(paymentType, (type) => {
        multiPayments.value = [];
        // Switching between orders swaps the whole field set: keep what each order already holds.
        if (activeOrderId.value !== seenOrderId) {
            seenOrderId = activeOrderId.value;
            return;
        }
        if (type === 'cash') {
            paidAmount.value = normalize(checkoutNet.value);
            cashReceived.value = normalize(checkoutNet.value);
        } else {
            // Partial starts at zero so the cashier states the deposit; credit pays nothing.
            paidAmount.value = '0.000';
            cashReceived.value = '0.000';
        }
    });
    watch(activeOrderId, (id) => {
        seenOrderId = id;
    });

    // Idempotency key lives per checkout attempt: any change to what is being sold starts a new one.
    const checkoutFingerprint = computed(() =>
        JSON.stringify([
            cart.value.map((i) => [i.id, i.quantity, i.unit_price, i.discount_type, i.discount_value]),
            discountType.value,
            discountValue.value,
            paymentType.value,
            paymentMethod.value,
            cashReceived.value,
            paidAmount.value,
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
        discountSummary,
        customerExpensesTotal,
        cartNetTotal,
        changeDue,
        applyDiscountPreset,
        openMultiPayment,
        confirmMultiPayment,
        applyExpenses,
    };
}
