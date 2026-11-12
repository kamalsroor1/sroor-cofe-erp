import { ref } from 'vue';
import api from '../Services/api';
import { useAuthStore } from '../stores/auth';
import { useTrans } from './useTrans';

/**
 * SET-02: the per-branch "selling needs an open shift" switch, listed in one place in Settings.
 * Same endpoint as the branch form: GET / PUT /stores/{id}/pos-settings/shift-rule. Turning it off while
 * that branch has an open shift is refused by the server (422 settings.shift_rule_open_shift); the
 * server's translated message is shown on that branch's row and the switch stays on.
 */
export function useBranchShiftRules() {
    const { t } = useTrans();
    const authStore = useAuthStore();

    const branches = ref([]);
    const isLoading = ref(false);
    const loadFailed = ref(false);
    const available = () => authStore.can('stores.manage') || authStore.can('settings.manage');

    const readRule = async (store) => {
        try {
            const res = await api.get(`/stores/${store.id}/pos-settings/shift-rule`);
            return {
                id: store.id,
                name: store.name,
                required: res.data?.data?.require_shift_for_sales !== false,
                unavailable: false,
                isSaving: false,
                error: '',
            };
        } catch {
            return { id: store.id, name: store.name, required: true, unavailable: true, isSaving: false, error: '' };
        }
    };

    const load = async () => {
        if (!available()) return;
        isLoading.value = true;
        loadFailed.value = false;
        try {
            const res = await api.get('/stores');
            const active = (res.data?.stores || []).filter((store) => store.is_active !== false);
            branches.value = await Promise.all(active.map(readRule));
        } catch {
            loadFailed.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    const setRequired = async (branch, required) => {
        if (branch.isSaving || branch.unavailable || branch.required === required) return false;
        branch.isSaving = true;
        branch.error = '';
        try {
            await api.put(`/stores/${branch.id}/pos-settings/shift-rule`, { require_shift_for_sales: required });
            branch.required = required;
            return true;
        } catch (error) {
            branch.error = error.response?.data?.message || error.userMessage || t('settings.shift_rule_save_failed');
            return false;
        } finally {
            branch.isSaving = false;
        }
    };

    return { available, branches, isLoading, loadFailed, load, setRequired };
}
