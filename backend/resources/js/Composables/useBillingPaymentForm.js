import { computed, reactive, ref, watch } from 'vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';
import { compareDecimalStrings, isValidDecimal, nowLocalInput, receiptErrorKey, todayIso } from '@/helpers/billing';

/**
 * #232 UI §2 / §2b: form state of the invoice screen (method, receipt, extras), validation that mirrors the
 * backend rules, and the upload with progress / cancel.
 * @param {import('vue').Ref<object|null>} invoice
 * @param {import('vue').Ref<Array>} methods  `payment_methods` of /billing/subscription (configured ones only)
 * @param {Function} submitter  (invoiceId, form, { onProgress, signal }) => Promise<{ ok, message, fields, canceled, networkFailed }>
 * @param {Function} onPaid
 */
export function useBillingPaymentForm(invoice, methods, submitter, onPaid) {
    const { t } = useTrans();
    const { formatMoney } = useFormatters();

    const form = reactive({ method: '', amount: '', payer_reference: '', paid_at: '', receipt: null });
    const errors = reactive({});
    const serverError = ref('');
    const uploading = ref(false);
    const percent = ref(0);
    const uploadFailed = ref(false);
    let controller = null;

    const remaining = computed(() => invoice.value?.remaining ?? '0');
    const selectedMethod = computed(() => methods.value.find((m) => m.key === form.method) || null);
    const canSubmit = computed(() => !!selectedMethod.value && !!form.receipt && !uploading.value);
    const isPartial = computed(() => {
        const amount = String(form.amount ?? '').trim();
        return (
            isValidDecimal(amount) &&
            compareDecimalStrings(amount, '0') > 0 &&
            compareDecimalStrings(amount, remaining.value) < 0
        );
    });

    const clearErrors = () => {
        Object.keys(errors).forEach((key) => delete errors[key]);
        serverError.value = '';
        uploadFailed.value = false;
    };

    const reset = () => {
        Object.assign(form, {
            // A single configured method is pre-selected; with several the shop must choose.
            method: methods.value.length === 1 ? methods.value[0].key : '',
            amount: invoice.value?.remaining ?? '',
            payer_reference: '',
            paid_at: '',
            receipt: null,
        });
        clearErrors();
        percent.value = 0;
    };

    watch(() => invoice.value?.id, reset, { immediate: true });
    watch(
        () => methods.value.map((m) => m.key).join(','),
        () => {
            if (!selectedMethod.value) form.method = methods.value.length === 1 ? methods.value[0].key : '';
        }
    );

    const validate = () => {
        const next = {};
        const amount = String(form.amount ?? '').trim();
        if (!isValidDecimal(amount) || compareDecimalStrings(amount, '0') <= 0)
            next.amount = t('subscription.billing.pay.errors.amount_invalid');
        else if (compareDecimalStrings(amount, remaining.value) > 0)
            next.amount = t('subscription.billing.pay.errors.amount_out_of_range', {
                remaining: formatMoney(remaining.value),
            });
        if (!selectedMethod.value) next.method = t('subscription.billing.pay.errors.method_required');
        const reference = form.payer_reference.trim();
        if (reference.length < 3 || reference.length > 100)
            next.payer_reference = t('subscription.billing.pay.errors.reference_length');
        if (form.paid_at && form.paid_at > nowLocalInput())
            next.paid_at = t('subscription.billing.pay.errors.paid_at_future');
        const receiptKey = receiptErrorKey(form.receipt);
        if (receiptKey) next.receipt = t(receiptKey);
        clearErrors();
        Object.assign(errors, next);
        return Object.keys(next).length === 0;
    };

    const submit = async () => {
        if (!validate()) return;
        uploading.value = true;
        percent.value = 0;
        controller = new AbortController();
        const result = await submitter(
            invoice.value.id,
            {
                ...form,
                method: selectedMethod.value.key,
                payer_reference: form.payer_reference.trim(),
                // The transfer time is only a hint for the reviewer: blank means "today".
                paid_at: form.paid_at || todayIso(),
            },
            { onProgress: (value) => (percent.value = value), signal: controller.signal }
        );
        uploading.value = false;
        controller = null;
        if (result.ok) {
            onPaid();
            return;
        }
        if (result.canceled) return;
        if (result.networkFailed) {
            uploadFailed.value = true;
            return;
        }
        serverError.value = result.message || '';
        Object.entries(result.fields || {}).forEach(([key, messages]) => {
            errors[key] = Array.isArray(messages) ? messages[0] : messages;
        });
    };

    const cancelUpload = () => controller?.abort();

    return {
        form,
        errors,
        serverError,
        uploading,
        percent,
        uploadFailed,
        selectedMethod,
        remaining,
        isPartial,
        canSubmit,
        submit,
        cancelUpload,
        reset,
    };
}
