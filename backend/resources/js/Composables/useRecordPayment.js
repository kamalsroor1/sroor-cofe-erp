import { ref } from 'vue';
import centralApi from '../Services/centralApi';
import { notifyError, notifySuccess, notifyWarning } from '../helpers/alert';
import { useTrans } from './useTrans';
import { toTenantDateString } from './useBusinessDate';

/**
 * "Record manual payment" of the tenant billing section (OPS-20):
 *   POST /super-admin/billing-invoices/{id}/record-payment { amount, method, reference?, note?, paid_at }
 *   -> 200 invoice resource; 422 billing.amount_out_of_range (details.remaining) / billing.payment_already_pending;
 *   409 billing.invoice_not_payable. Step-up is handled by centralApi. The server owns every amount check.
 */

export const RECORD_METHODS = Object.freeze(['cash', 'instapay', 'vodafone_cash', 'bank_transfer']);

const today = () => toTenantDateString();

const emptyForm = (remaining = '') => ({
    amount: remaining,
    method: 'cash',
    reference: '',
    note: '',
    paid_at: today(),
});

export function useRecordPayment(onRecorded) {
    const { t } = useTrans();

    const isOpen = ref(false);
    const isSubmitting = ref(false);
    const invoice = ref(null);
    const form = ref(emptyForm());
    const errors = ref({});

    const open = (target) => {
        invoice.value = target;
        form.value = emptyForm(String(target?.remaining ?? ''));
        errors.value = {};
        isOpen.value = true;
    };

    const close = () => {
        if (isSubmitting.value) return;
        isOpen.value = false;
    };

    const setField = (key, value) => {
        form.value = { ...form.value, [key]: value };
        if (errors.value[key]) {
            const next = { ...errors.value };
            delete next[key];
            errors.value = next;
        }
    };

    const buildPayload = () => {
        const current = form.value;
        const payload = {
            amount: String(current.amount).trim(),
            method: current.method,
            paid_at: current.paid_at,
        };
        if (current.reference.trim() !== '') payload.reference = current.reference.trim();
        if (current.note.trim() !== '') payload.note = current.note.trim();
        return payload;
    };

    const submit = async () => {
        if (isSubmitting.value || !invoice.value) return;
        isSubmitting.value = true;
        errors.value = {};
        try {
            const response = await centralApi.post(
                `/super-admin/billing-invoices/${invoice.value.id}/record-payment`,
                buildPayload()
            );
            notifySuccess(response.data?.message || '');
            isOpen.value = false;
            await onRecorded?.(response.data?.data);
        } catch (error) {
            const data = error.response?.data;
            if (error.stepUpCancelled) {
                notifyWarning(error.userMessage);
            } else if (error.response?.status === 422 && data?.errors) {
                errors.value = data.errors;
                notifyError(t('billing.console.record.failed'), error.userMessage || '');
            } else if (error.response?.status === 422 && data?.error_code === 'billing.amount_out_of_range') {
                errors.value = { amount: [data.message] };
            } else {
                notifyError(t('billing.console.record.failed'), error.userMessage || '');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    return { isOpen, isSubmitting, invoice, form, errors, open, close, setField, submit };
}
