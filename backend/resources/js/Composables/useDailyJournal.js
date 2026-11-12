import { ref, reactive, nextTick, onMounted, onBeforeUnmount } from 'vue';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { businessToday } from './useBusinessDate';
import { normalize } from '../helpers/decimal';
import { isOwnShiftJournal, canPrintJournal } from '../helpers/journalScope';
import { confirmDialog } from '../helpers/alert';

const Z_PRINT_FALLBACK_MS = 60000;

export function useDailyJournal() {
    const { t } = useTrans();

    const selectedDate = ref(businessToday());
    const activeTab = ref('invoices');
    const isLoading = ref(false);
    const hasError = ref(false);
    const errorMessage = ref('');
    const isSubmitting = ref(false);

    const activeShift = ref(null);
    const isOwnShiftScope = ref(false);
    const canPrint = ref(false);
    const zReport = ref(null);
    const summary = ref({
        total_sales: 0,
        cash_sales: 0,
        credit_sales: 0,
        customer_payments: 0,
        total_cash_in: 0,
        supplier_payments: 0,
        total_expenses: 0,
        cash_expenses: 0,
        total_cash_out: 0,
        net_cash_today: 0,
        opening_cash_balance: 0,
        expected_cash_in_drawer: null,
    });
    const invoices = ref([]);
    const expenses = ref([]);

    // Modals State
    const showOpenShiftModal = ref(false);
    const openShiftForm = reactive({
        opening_cash_balance: '0.000',
        notes: '',
    });

    const showCloseShiftModal = ref(false);
    const closeShiftForm = reactive({
        actual_cash_balance: '',
        notes: '',
    });

    const showExpenseModal = ref(false);
    const expenseForm = reactive({
        title: '',
        amount: '',
        cost_center: 'operational',
        payment_method: 'cash',
        notes: '',
    });

    const fetchDailyJournal = async () => {
        isLoading.value = true;
        hasError.value = false;
        errorMessage.value = '';
        try {
            const response = await api.get('/daily-journal', {
                params: {
                    date: selectedDate.value,
                },
            });
            const data = response.data?.data;
            if (data) {
                activeShift.value = data.active_shift;
                isOwnShiftScope.value = isOwnShiftJournal(data);
                canPrint.value = canPrintJournal(data);
                summary.value = data.summary || {};
                invoices.value = data.invoices || [];
                expenses.value = data.expenses || [];
            }
        } catch (error) {
            console.error('Failed to load daily journal:', error);
            hasError.value = true;
            errorMessage.value = error?.response?.data?.message || '';
        } finally {
            isLoading.value = false;
        }
    };

    const updateOpenShiftField = (field, val) => {
        openShiftForm[field] = val;
    };

    const updateCloseShiftField = (field, val) => {
        closeShiftForm[field] = val;
    };

    const updateExpenseField = (field, val) => {
        expenseForm[field] = val;
    };

    const submitOpenShift = async () => {
        isSubmitting.value = true;
        try {
            await api.post('/shifts/open', openShiftForm);
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('treasury.shift_opened_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            showOpenShiftModal.value = false;
            await fetchDailyJournal();
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('common.error'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    const openCloseShiftModal = () => {
        const expected = summary.value?.expected_cash_in_drawer;
        // Expected cash is withheld from a cashier: leave the counted-cash field empty instead of pre-filling 0.
        closeShiftForm.actual_cash_balance = expected === null || expected === undefined ? '' : normalize(expected);
        closeShiftForm.notes = '';
        showCloseShiftModal.value = true;
    };

    const submitCloseShift = async () => {
        if (!activeShift.value) return;
        const shiftIdBeforeClose = activeShift.value.id;
        isSubmitting.value = true;
        let closedShiftId;
        let closeMessage;
        try {
            const response = await api.post('/shifts/close', {
                shift_id: shiftIdBeforeClose,
                actual_cash_balance: closeShiftForm.actual_cash_balance,
                notes: closeShiftForm.notes,
            });
            closedShiftId = response.data?.data?.id ?? shiftIdBeforeClose;
            closeMessage = response.data?.message || t('treasury.shift_closed_success');
            showCloseShiftModal.value = false;
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('common.error'),
            });
            return;
        } finally {
            isSubmitting.value = false;
        }

        await fetchDailyJournal();
        const wantsPrint = await confirmDialog({
            title: t('common.success'),
            text: closeMessage,
            icon: 'success',
            confirmButtonText: t('treasury.print_z_report'),
            cancelButtonText: t('common.close'),
        });
        if (wantsPrint) await printShiftZReport(closedShiftId);
    };

    const submitExpense = async () => {
        isSubmitting.value = true;
        try {
            await api.post('/expenses', {
                ...expenseForm,
                category: t('expenses.daily_category'),
                expense_date: selectedDate.value,
            });
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('expenses.expense_added'),
                timer: 1500,
                showConfirmButton: false,
            });
            showExpenseModal.value = false;
            expenseForm.title = '';
            expenseForm.amount = '';
            await fetchDailyJournal();
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('common.error'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

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

    const printShiftZReport = async (shiftId) => {
        if (!shiftId) return;
        try {
            const response = await api.get(`/shifts/${shiftId}/z-report`);
            const report = response.data?.report;
            if (!report) return;
            zReport.value = report;
            await nextTick();
            startPrintMode();
            setTimeout(() => window.print(), 150);
        } catch (error) {
            finishPrintMode();
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('common.error'),
            });
        }
    };

    const printActiveZReport = () => printShiftZReport(activeShift.value?.id);

    onBeforeUnmount(finishPrintMode);

    onMounted(() => {
        fetchDailyJournal();
    });

    return {
        selectedDate,
        activeTab,
        isLoading,
        hasError,
        errorMessage,
        isSubmitting,
        activeShift,
        isOwnShiftScope,
        canPrint,
        zReport,
        summary,
        invoices,
        expenses,
        showOpenShiftModal,
        openShiftForm,
        showCloseShiftModal,
        closeShiftForm,
        showExpenseModal,
        expenseForm,
        fetchDailyJournal,
        updateOpenShiftField,
        updateCloseShiftField,
        updateExpenseField,
        submitOpenShift,
        openCloseShiftModal,
        submitCloseShift,
        submitExpense,
        printActiveZReport,
    };
}
