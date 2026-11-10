import { ref, computed, watch } from 'vue';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { useFormatters } from './useFormatters';
import {
    REFUND_CASH,
    REFUND_BALANCE,
    defaultRefundMethod,
    exceedsCashRefundable,
    isWalkInCustomer,
    isCashRefundCapError,
    refundErrorMessage,
} from '../helpers/refundMethod';

/**
 * Refund choice (cash / customer balance) of a sales return.
 * - `source`: ref to the picked invoice payload (`is_walk_in`, `cash_refundable_amount`), or null.
 * - `total`: ref to the return total (string).
 * - `partyId`: ref to the customer; only looked up (walk-in flag) when no source invoice is picked.
 */
export function useRefundMethod({ source, total, partyId }) {
    const { t } = useTrans();
    const { formatMoney } = useFormatters();
    const choice = ref(null);
    const customerWalkIn = ref(false);
    const capError = ref('');
    let token = 0;

    const isWalkIn = computed(() => (source.value ? source.value.is_walk_in === true : customerWalkIn.value));
    const refundable = computed(() => source.value?.cash_refundable_amount ?? null);
    const totalValue = computed(() => String(total.value ?? '0'));

    const method = computed(
        () =>
            choice.value ??
            defaultRefundMethod({ walkIn: isWalkIn.value, refundable: refundable.value, total: totalValue.value })
    );

    // Cash stays selectable: the server cap (422) is the final word, this is only a heads-up.
    const hint = computed(() =>
        !isWalkIn.value && exceedsCashRefundable({ refundable: refundable.value, total: totalValue.value })
            ? t('returns.cash_refundable_hint', { amount: formatMoney(refundable.value) })
            : ''
    );

    watch(
        () => [source.value?.id ?? null, partyId?.value ?? null],
        async ([sourceId, id]) => {
            const current = ++token;
            choice.value = null;
            capError.value = '';
            customerWalkIn.value = false;
            if (sourceId || !id) return;
            try {
                const res = await api.get(`/customers/${id}`);
                if (current === token) customerWalkIn.value = isWalkInCustomer(res.data?.data);
            } catch {
                // Unknown customer type: keep the balance default; the server still forces cash for walk-in.
            }
        },
        { immediate: true }
    );

    // A cap rejection is about the total that was sent; a new total makes it stale.
    watch(totalValue, () => {
        capError.value = '';
    });

    const setMethod = (value) => {
        if (isWalkIn.value) return;
        choice.value = value === REFUND_CASH ? REFUND_CASH : REFUND_BALANCE;
        capError.value = '';
    };

    /** Shows the cash-cap rejection inline; returns true when the error was that one (nothing else to report). */
    const handleCapError = (error) => {
        if (!isCashRefundCapError(error)) return false;
        capError.value = refundErrorMessage(error, '');
        return true;
    };

    return { method, isWalkIn, locked: isWalkIn, hint, capError, setMethod, handleCapError };
}
