import { ref } from 'vue';
import api from '../../Services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import { newUuid } from '../../helpers/uuid';
import { normalize, isPositive } from '../../helpers/decimal';

/**
 * Checkout submission: validation, idempotent POST /invoices, success modal state and printing.
 */
export function usePosSubmit({
    fields,
    activeOrder,
    activeStore,
    isOnline,
    buildPayment,
    buildPayloadLines,
    saveOrders,
    clearActiveOrder,
    focusSearch,
    printLastInvoice,
    lastCreatedInvoice,
    sounds,
}) {
    const { cart, selectedCustomerId, paymentType, paymentMethod, discountType } = fields;

    const isSubmitting = ref(false);
    const showSuccessModal = ref(false);
    const lastChangeAmount = ref('0.000');

    const submitInvoice = async (printImmediately = false) => {
        if (isSubmitting.value) return;
        if (!isOnline.value) {
            sounds.playErrorTone();
            Swal.fire({
                icon: 'warning',
                title: trans('connectivity.checkout_blocked'),
                text: trans('connectivity.checkout_blocked_desc'),
            });
            return;
        }
        if (cart.value.length === 0) {
            Swal.fire({ icon: 'warning', title: trans('pos.empty_cart_error'), timer: 1500, showConfirmButton: false });
            return;
        }

        const payment = buildPayment();
        if (!payment.valid) {
            Swal.fire({
                icon: 'warning',
                title: trans(payment.reason === 'tender_short' ? 'pos.tender_short' : 'pos.partial_amount_invalid'),
            });
            return;
        }

        // Reused on retry after an error/timeout: the first request may already have committed.
        if (!activeOrder.value.checkoutUuid) activeOrder.value.checkoutUuid = newUuid();
        const checkoutUuid = activeOrder.value.checkoutUuid;
        saveOrders();

        isSubmitting.value = true;
        try {
            const payload = {
                client_uuid: checkoutUuid,
                store_id: activeStore.value?.id || 1,
                customer_id: selectedCustomerId.value,
                payment_type: paymentType.value,
                payment_method: paymentType.value === 'credit' ? null : paymentMethod.value,
                discount_type: discountType.value,
                ...buildPayloadLines(),
                paid_amount: payment.paidAmount,
                payments: payment.payments,
            };

            const res = await api.post('/invoices', payload, { headers: { 'Idempotency-Key': checkoutUuid } });
            lastCreatedInvoice.value = res.data?.data;
            // The server owns the change; the panel value was only a preview.
            lastChangeAmount.value = normalize(res.data?.data?.change_amount ?? '0');
            sounds.playSuccessChime();

            if (printImmediately && lastCreatedInvoice.value?.id) {
                printLastInvoice();
                // The cashier still has to see the change to hand back.
                if (isPositive(lastChangeAmount.value)) showSuccessModal.value = true;
            } else {
                showSuccessModal.value = true;
            }

            // Clear completed order & switch to remaining or fresh order
            if (activeOrder.value) activeOrder.value.checkoutUuid = null;
            clearActiveOrder();
            focusSearch();
        } catch (e) {
            sounds.playErrorTone();
            // 401 and 403 are already handled (redirect / alert) by the api interceptor.
            const handledByInterceptor = [401, 403].includes(e.response?.status);
            if (!handledByInterceptor) {
                Swal.fire({
                    icon: 'error',
                    title: trans('pos.checkout_failed'),
                    text: e.userMessage || trans('pos.checkout_failed_desc'),
                });
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    return { isSubmitting, showSuccessModal, lastChangeAmount, submitInvoice };
}
