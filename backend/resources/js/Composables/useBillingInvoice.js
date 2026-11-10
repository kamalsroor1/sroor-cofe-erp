import { ref } from 'vue';
import api from '@/Services/api';
import { trans } from '@/helpers/trans';

/** One own billing invoice (print view). */
export function useBillingInvoice() {
    const invoice = ref(null);
    const isLoading = ref(true);
    const error = ref('');

    const load = async (id) => {
        isLoading.value = true;
        error.value = '';
        try {
            const res = await api.get(`/billing/invoices/${id}`);
            invoice.value = res.data.data;
        } catch (e) {
            error.value = e.userMessage || trans('subscription.billing.print.not_found');
        } finally {
            isLoading.value = false;
        }
    };

    return { invoice, isLoading, error, load };
}
