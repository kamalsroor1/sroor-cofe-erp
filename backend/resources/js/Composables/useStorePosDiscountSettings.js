import { ref } from 'vue';
import api from '../Services/api';
import { notifyError, notifySuccess } from '../helpers/alert';
import { sanitizeAmountInput } from '../helpers/posCheckout';
import { trans } from '../helpers/trans';

/** The default cap when a branch never saved one (mirrors StorePosSetting::DEFAULT_MAX_DISCOUNT_PERCENT). */
export const DEFAULT_MAX_DISCOUNT_PERCENT = '5';

/**
 * POS-07: the branch's `max_discount_percent` (0 to 100, % of a sale a cashier may discount without a manager).
 * Loads and saves only that key of GET/PUT /stores/{id}/pos-settings; blank means "use the default".
 */
export function useStorePosDiscountSettings() {
    const value = ref('');
    const isLoading = ref(false);
    const isSaving = ref(false);
    const loadFailed = ref(false);
    const error = ref('');

    const load = async (storeId) => {
        isLoading.value = true;
        loadFailed.value = false;
        error.value = '';
        try {
            const res = await api.get(`/stores/${storeId}/pos-settings`);
            const stored = res.data?.data?.max_discount_percent;
            value.value = stored === null || stored === undefined ? '' : String(Number(stored));
        } catch {
            loadFailed.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    const onInput = (raw) => {
        value.value = sanitizeAmountInput(raw);
        error.value = '';
    };

    /** Client-side check only for fast feedback; the server validates 0..100 with up to 3 decimals. */
    const validate = () => {
        if (value.value === '') return true;
        const number = Number(value.value);
        const decimals = value.value.split('.')[1]?.length ?? 0;
        if (!Number.isFinite(number) || number < 0 || number > 100 || decimals > 3) {
            error.value = trans('inventory.max_discount_percent_invalid');
            return false;
        }
        return true;
    };

    const save = async (storeId) => {
        if (!validate()) return false;
        isSaving.value = true;
        try {
            await api.put(`/stores/${storeId}/pos-settings`, {
                max_discount_percent: value.value === '' ? null : value.value,
            });
            notifySuccess(trans('inventory.max_discount_percent_saved'));
            return true;
        } catch (e) {
            error.value = e.response?.data?.errors?.max_discount_percent?.[0] || e.userMessage || '';
            if (!error.value) notifyError(trans('common.error'));
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    return { value, isLoading, isSaving, loadFailed, error, load, onInput, save };
}
