import { ref, computed } from 'vue';
import api from '../../Services/api';
import { useTrans } from '../useTrans';
import { useReturnSource } from '../useReturnSource';
import { usePosBusinessDate } from './usePosBusinessDate';
import { mapApiError } from '../../helpers/planFeatures';
import { useRefundMethod } from '../useRefundMethod';
import { notifySuccess, notifyError } from '../../helpers/alert';

/**
 * POS-11: book a sales return against one invoice picked in the recent-sales drawer.
 * Lines and remaining quantities come from /return-sources/sales_return/{id}; the server prices them.
 */
export function usePosQuickReturn({ isOnline, onDone }) {
    const { t } = useTrans();
    const { refreshBusinessDate } = usePosBusinessDate();
    const returnType = ref('sales_return');
    const source = useReturnSource(returnType);
    const refund = useRefundMethod({ source: source.selected, total: source.total });
    const show = ref(false);
    const isSubmitting = ref(false);

    const open = async (invoice) => {
        show.value = true;
        source.clearSource();
        await source.selectSource({ id: invoice.id });
    };

    const close = () => {
        show.value = false;
        source.clearSource();
    };

    const canSubmit = computed(
        () =>
            isOnline.value && !isSubmitting.value && !source.isLoadingLines.value && source.chosenLines.value.length > 0
    );

    const submit = async () => {
        if (!canSubmit.value || !source.selected.value) return;
        isSubmitting.value = true;
        try {
            const returnDate = await refreshBusinessDate();
            if (!returnDate) throw new Error('no business date');
            const res = await api.post('/returns', {
                return_type: 'sales_return',
                invoice_id: source.selected.value.id,
                customer_id: source.selected.value.party_id,
                return_date: returnDate,
                refund_method: refund.method.value,
                items: source.payloadItems(),
            });
            notifySuccess(res.data?.message || t('pos.quick_return_success'));
            close();
            onDone?.();
        } catch (error) {
            if (refund.handleCapError(error)) return;
            notifyError(
                mapApiError(error, {
                    t,
                    forbidden: 'pos.quick_return_forbidden',
                    featureUnavailable: 'pos.quick_return_feature_unavailable',
                    notFound: 'pos.quick_return_not_found',
                    fallback: t('pos.quick_return_failed'),
                })
            );
        } finally {
            isSubmitting.value = false;
        }
    };

    const setQuantity = (line, value, inputEl) => {
        source.setQuantity(line, value);
        // Vue won't re-render when the stored value did not change (typed 50, still 5): resync the DOM.
        if (inputEl) inputEl.value = line.return_quantity;
    };

    return { setQuantity, refund, show, isSubmitting, canSubmit, source, open, close, submit };
}
