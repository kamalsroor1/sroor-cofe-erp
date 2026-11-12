import { ref } from 'vue';
import api from '../Services/api';
import { useAuthStore } from '../stores/auth';

/**
 * POS-10: the per-branch "require an open shift to sell" switch, stored with the branch's POS settings
 * (GET/PUT /stores/{id}/pos-settings/shift-rule, allowed for stores.manage or settings.manage). `available` stays false for users who cannot read the settings,
 * so the branch form simply hides the switch for them.
 */
export function useStoreShiftSetting() {
    const authStore = useAuthStore();
    const available = ref(false);
    const value = ref(true);
    const original = ref(true);
    // SET-02: the server's reason when a save is refused (e.g. turning it off while a shift is open).
    const error = ref('');

    const reset = () => {
        error.value = '';
        available.value = false;
        value.value = true;
        original.value = true;
    };

    const load = async (storeId) => {
        reset();
        // Skip the request (and the global 403 alert) for users who cannot manage branches or settings.
        if (!authStore.can('stores.manage') && !authStore.can('settings.manage')) return;
        try {
            const res = await api.get(`/stores/${storeId}/pos-settings/shift-rule`);
            const current = res.data?.data?.require_shift_for_sales;
            value.value = current !== false;
            original.value = value.value;
            available.value = true;
        } catch {
            reset();
        }
    };

    /** Saves only when the switch changed. Returns false when saving failed. */
    const save = async (storeId) => {
        if (!available.value || value.value === original.value) return true;
        error.value = '';
        try {
            await api.put(`/stores/${storeId}/pos-settings/shift-rule`, { require_shift_for_sales: value.value });
            original.value = value.value;
            return true;
        } catch (err) {
            error.value = err.response?.data?.message || '';
            return false;
        }
    };

    return { available, value, error, load, save, reset };
}
