import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import api from '../../Services/api';
import { trans } from '../../helpers/trans';
import { normalize } from '../../helpers/decimal';
import { sanitizeDecimalInput } from '../../helpers/posWeight';

/**
 * POS-10: open / close the active branch's cash shift from the POS and show its X-report.
 * The shift state itself lives in usePosCatalog (so a store switch reloads it); this composable
 * only drives the gate, the shift menu and the two write calls.
 */
export function usePosShift({ authStore, activeShift, requiresShift, isLoading, reloadShift, isOnline }) {
    const showPanel = ref(false);
    const openingCash = ref('0');
    const countedCash = ref('');
    const isSubmitting = ref(false);
    const isReportLoading = ref(false);
    const errorMessage = ref('');
    const reportFailed = ref(false);

    const canManageShift = computed(() => authStore.can('daily_journal.close_shift'));
    // The gate covers the workspace only when the branch needs a shift and none is open.
    const gateVisible = computed(() => !isLoading.value && requiresShift.value && !activeShift.value);

    const setOpeningCash = (value) => {
        openingCash.value = sanitizeDecimalInput(value);
    };
    const setCountedCash = (value) => {
        countedCash.value = sanitizeDecimalInput(value);
    };

    const refreshReport = async () => {
        isReportLoading.value = true;
        reportFailed.value = false;
        try {
            await reloadShift();
        } catch {
            reportFailed.value = true;
        } finally {
            isReportLoading.value = false;
        }
    };

    const openPanel = async () => {
        errorMessage.value = '';
        openingCash.value = '0';
        countedCash.value = '';
        showPanel.value = true;
        if (activeShift.value) await refreshReport();
    };

    const closePanel = () => {
        showPanel.value = false;
    };

    const guardOnline = () => {
        if (isOnline.value) return true;
        errorMessage.value = trans('connectivity.checkout_blocked_desc');
        return false;
    };

    const submitOpen = async () => {
        if (isSubmitting.value || !guardOnline()) return;
        isSubmitting.value = true;
        errorMessage.value = '';
        try {
            await api.post('/shifts/open', { opening_cash_balance: normalize(openingCash.value || '0') });
            showPanel.value = false;
            await reloadShift();
        } catch (e) {
            if (e.response?.status !== 403) errorMessage.value = e.userMessage || trans('pos.shift_open_failed');
        } finally {
            isSubmitting.value = false;
        }
    };

    const submitClose = async () => {
        if (isSubmitting.value || !activeShift.value || !guardOnline()) return;
        if (countedCash.value === '') {
            errorMessage.value = trans('pos.shift_close_counted_cash');
            return;
        }
        isSubmitting.value = true;
        errorMessage.value = '';
        try {
            const res = await api.post('/shifts/close', {
                shift_id: activeShift.value.id,
                actual_cash_balance: normalize(countedCash.value),
            });
            showPanel.value = false;
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: res.data?.message || '',
            });
            await reloadShift();
        } catch (e) {
            if (e.response?.status !== 403) errorMessage.value = e.userMessage || trans('pos.shift_close_failed');
        } finally {
            isSubmitting.value = false;
        }
    };

    return {
        showPanel,
        openingCash,
        countedCash,
        isSubmitting,
        isReportLoading,
        reportFailed,
        errorMessage,
        canManageShift,
        gateVisible,
        setOpeningCash,
        setCountedCash,
        refreshReport,
        openPanel,
        closePanel,
        submitOpen,
        submitClose,
    };
}
