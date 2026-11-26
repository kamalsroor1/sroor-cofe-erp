import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../Services/api';
import { trans } from '../../helpers/trans';
import { normalize } from '../../helpers/decimal';
import { isValidCountedCash, canCloseShift, isShiftOpenedBy } from '../../helpers/shiftCash';
import { useShiftCloseFigures } from '../useShiftCloseFigures';
import { useZReportPrint } from '../useZReportPrint';
import { useAppConfigStore } from '../../stores/appConfig';
import { sanitizeDecimalInput } from '../../helpers/posWeight';

/**
 * POS-10: open / close the active branch's cash shift from the POS and show its X-report.
 * The shift state itself lives in usePosCatalog (so a store switch reloads it); this composable
 * only drives the gate, the shift menu and the two write calls.
 */
export function usePosShift({ authStore, activeShift, requiresShift, isLoading, reloadShift, isOnline }) {
    const router = useRouter();
    const figures = useShiftCloseFigures();
    const appConfigStore = useAppConfigStore();
    const zPrint = useZReportPrint();
    const showPanel = ref(false);
    const openingCash = ref('0');
    const countedCash = ref('');
    const isSubmitting = ref(false);
    const isReportLoading = ref(false);
    const errorMessage = ref('');
    const reportFailed = ref(false);
    let lastClosedShiftId = null;

    const canManageShift = computed(() => authStore.can('daily_journal.close_shift'));
    // DEC-SHIFT: only the shift's opener or a manager (reports.view) closes it; the server answers 403 otherwise.
    const canCloseActiveShift = computed(() =>
        canCloseShift({
            canClose: canManageShift.value,
            isManager: authStore.can('reports.view'),
            isOwn: isShiftOpenedBy(activeShift.value, authStore.user?.id),
        })
    );
    // The gate covers the workspace only when the branch needs a shift and none is open.
    const gateVisible = computed(() => !isLoading.value && requiresShift.value && !activeShift.value);

    const setOpeningCash = (value) => {
        openingCash.value = sanitizeDecimalInput(value);
    };
    const setCountedCash = (value) => {
        countedCash.value = sanitizeDecimalInput(value);
    };

    // The shift's figures come from GET /shifts/current, read fresh each time the panel opens. Blind count: the
    // server withholds the expected cash from a user without reports.view, and the panel never shows it before the close.
    const refreshReport = async () => {
        isReportLoading.value = true;
        reportFailed.value = false;
        try {
            await figures.load();
            reportFailed.value = figures.failed.value;
            if (!figures.failed.value) await reloadShift();
        } finally {
            isReportLoading.value = false;
        }
    };

    const openPanel = async () => {
        errorMessage.value = '';
        openingCash.value = '0';
        countedCash.value = '';
        figures.reset();
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

    // The "shift required" gate is a full-screen block: give it a way out to the previous screen, or home.
    const leaveGate = () => {
        if (window.history.state?.back) router.back();
        else router.push({ name: 'dashboard' });
    };

    const retryClosedReport = () => zPrint.presentClosedShift(lastClosedShiftId);
    const printClosedReport = () => zPrint.printReport(zPrint.closedReport.value);

    const submitOpen = async () => {
        if (isSubmitting.value || !guardOnline()) return;
        isSubmitting.value = true;
        errorMessage.value = '';
        try {
            await api.post('/shifts/open', { opening_cash_balance: normalize(openingCash.value || '0') });
            showPanel.value = false;
            await Promise.all([reloadShift(), appConfigStore.refreshActiveShift()]);
        } catch (e) {
            if (e.response?.status !== 403) errorMessage.value = e.userMessage || trans('pos.shift_open_failed');
        } finally {
            isSubmitting.value = false;
        }
    };

    const submitClose = async () => {
        if (isSubmitting.value || !activeShift.value || isReportLoading.value || reportFailed.value) return;
        if (!canCloseActiveShift.value) return;
        if (!guardOnline()) return;
        if (!isValidCountedCash(countedCash.value)) {
            errorMessage.value = trans('treasury.counted_cash_required');
            return;
        }
        isSubmitting.value = true;
        errorMessage.value = '';
        try {
            const shiftId = figures.shiftId.value ?? activeShift.value.id;
            const res = await api.post('/shifts/close', {
                shift_id: shiftId,
                actual_cash_balance: normalize(countedCash.value),
            });
            lastClosedShiftId = res.data?.data?.id ?? shiftId;
            showPanel.value = false;
            // The close succeeded: show the final Z report; a failed report fetch is handled inside that dialog.
            zPrint.presentClosedShift(lastClosedShiftId);
            await Promise.all([reloadShift(), appConfigStore.refreshActiveShift()]);
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
        canCloseActiveShift,
        reportError: figures.errorMessage,
        zPrint: reactive(zPrint),
        leaveGate,
        retryClosedReport,
        printClosedReport,
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
