import { ref, nextTick, onBeforeUnmount } from 'vue';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

const Z_PRINT_FALLBACK_MS = 60000;

/**
 * Z / X report of a shift: the on-screen result shown right after a close, and the 80mm print sheet
 * (ShiftZReportPrint). A failed report fetch never hides that the close itself succeeded.
 */
export function useZReportPrint() {
    const { t } = useTrans();

    // Report currently rendered by the hidden print sheet.
    const zReport = ref(null);
    // Result dialog shown after a successful close.
    const showClosedResult = ref(false);
    const closedReport = ref(null);
    const closedLoading = ref(false);
    const closedFailed = ref(false);
    const closedErrorMessage = ref('');

    let endPrintMode = null;

    const finishPrintMode = () => {
        if (!endPrintMode) return;
        const end = endPrintMode;
        endPrintMode = null;
        end();
    };

    const startPrintMode = () => {
        finishPrintMode();
        document.body.classList.remove('print-a4-mode', 'print-thermal-mode');
        document.body.classList.add('print-z-report-mode');

        const mql = typeof window.matchMedia === 'function' ? window.matchMedia('print') : null;
        const onMediaChange = (event) => {
            if (!event.matches) finishPrintMode();
        };
        const fallbackTimer = setTimeout(finishPrintMode, Z_PRINT_FALLBACK_MS);

        window.addEventListener('afterprint', finishPrintMode, { once: true });
        mql?.addEventListener?.('change', onMediaChange);

        endPrintMode = () => {
            clearTimeout(fallbackTimer);
            window.removeEventListener('afterprint', finishPrintMode);
            mql?.removeEventListener?.('change', onMediaChange);
            document.body.classList.remove('print-z-report-mode');
            zReport.value = null;
        };
    };

    const printReport = async (report) => {
        if (!report) return;
        zReport.value = report;
        await nextTick();
        startPrintMode();
        setTimeout(() => window.print(), 150);
    };

    const fetchReport = async (shiftId) => {
        const response = await api.get(`/shifts/${shiftId}/z-report`);
        return response.data?.report || null;
    };

    const printShiftZReport = async (shiftId) => {
        if (!shiftId) return;
        try {
            await printReport(await fetchReport(shiftId));
        } catch (error) {
            finishPrintMode();
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('common.error'),
            });
        }
    };

    const loadClosedReport = async (shiftId) => {
        closedReport.value = null;
        closedFailed.value = false;
        closedErrorMessage.value = '';
        closedLoading.value = true;
        try {
            closedReport.value = await fetchReport(shiftId);
            closedFailed.value = !closedReport.value;
        } catch (error) {
            closedFailed.value = true;
            closedErrorMessage.value = error.userMessage || '';
        } finally {
            closedLoading.value = false;
        }
    };

    const presentClosedShift = (shiftId) => {
        showClosedResult.value = true;
        return loadClosedReport(shiftId);
    };

    const dismissClosedResult = () => {
        showClosedResult.value = false;
    };

    onBeforeUnmount(finishPrintMode);

    return {
        zReport,
        showClosedResult,
        closedReport,
        closedLoading,
        closedFailed,
        closedErrorMessage,
        printReport,
        printShiftZReport,
        presentClosedShift,
        dismissClosedResult,
    };
}
