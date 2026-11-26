import { ref } from 'vue';
import api from '@/Services/api';
import { trans } from '@/helpers/trans';
import { notifySuccess, notifyError } from '@/helpers/alert';
import { publishTemporaryAccess } from '@/Composables/useTemporaryAccess';

const INVOICES_PER_PAGE = 50;

/** Subscription & billing page state: plan/usage, invoice list, renewal invoice and payment submit. */
export function useSubscription() {
    const subscription = ref(null);
    const invoices = ref([]);
    const isLoading = ref(true);
    const error = ref('');

    const refreshInvoices = async () => {
        const [subRes, invRes] = await Promise.all([
            api.get('/billing/subscription'),
            api.get('/billing/invoices', { params: { per_page: INVOICES_PER_PAGE } }),
        ]);
        subscription.value = subRes.data.data;
        publishTemporaryAccess(subRes.data.data);
        invoices.value = invRes.data.data || [];
    };

    const load = async () => {
        isLoading.value = true;
        error.value = '';
        try {
            await refreshInvoices();
        } catch (e) {
            error.value = e.userMessage || trans('subscription.billing.page.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    /** Returns the created invoice, or the id/number of the open one when the API reports a duplicate. */
    const requestRenewal = async (cycle) => {
        try {
            const res = await api.post('/billing/renewal-invoice', { cycle });
            await refreshInvoices();
            notifySuccess(res.data.message);
            return { invoiceId: res.data.data.id, existing: false };
        } catch (e) {
            const data = e.response?.data;
            if (e.response?.status === 409 && data?.error_code === 'billing.invoice_duplicate') {
                notifySuccess(
                    trans('subscription.billing.renewal.existing_opened', {
                        number: data.details?.invoice_number ?? '',
                    })
                );
                return { invoiceId: data.details?.invoice_id ?? null, existing: true };
            }
            notifyError(e.userMessage);
            return null;
        }
    };

    /** Returns { ok, message, fields, networkFailed, canceled }: server 422 text is handed back for inline display. */
    const submitPayment = async (invoiceId, form, { onProgress, signal } = {}) => {
        const body = new FormData();
        body.append('receipt', form.receipt);
        body.append('method', form.method);
        body.append('amount', String(form.amount));
        body.append('payer_reference', form.payer_reference);
        body.append('paid_at', form.paid_at);
        try {
            const res = await api.post(`/billing/invoices/${invoiceId}/payments`, body, {
                signal,
                onUploadProgress: (event) => {
                    if (onProgress && event.total)
                        onProgress(Math.min(100, Math.round((event.loaded / event.total) * 100)));
                },
            });
            notifySuccess(res.data.message);
            await refreshInvoices();
            return { ok: true };
        } catch (e) {
            return {
                ok: false,
                message: e.userMessage,
                fields: e.response?.data?.errors || {},
                canceled: e.code === 'ERR_CANCELED',
                networkFailed: !e.response && e.code !== 'ERR_CANCELED',
            };
        }
    };

    /** SELL-07: withdraw the shop's own pending payment so a corrected one can be sent. */
    const cancelPayment = async (paymentId) => {
        try {
            const res = await api.post(`/billing/payments/${paymentId}/cancel`);
            notifySuccess(res.data.message);
            await refreshInvoices();
            return true;
        } catch (e) {
            notifyError(e.userMessage);
            return false;
        }
    };

    return {
        subscription,
        invoices,
        isLoading,
        error,
        load,
        requestRenewal,
        refreshInvoices,
        submitPayment,
        cancelPayment,
    };
}
