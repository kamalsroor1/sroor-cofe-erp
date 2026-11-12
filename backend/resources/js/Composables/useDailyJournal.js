import { ref, reactive, onMounted } from 'vue';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { businessToday } from './useBusinessDate';
import { normalize } from '../helpers/decimal';
import { isValidCountedCash } from '../helpers/shiftCash';
import { useShiftCloseFigures } from './useShiftCloseFigures';
import { useZReportPrint } from './useZReportPrint';
import { isOwnShiftJournal, canPrintJournal } from '../helpers/journalScope';

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
    const closeFigures = useShiftCloseFigures();
    const zPrint = useZReportPrint();
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

    let lastClosedShiftId = null;

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
        // The counted field starts empty on purpose (blind count): the cashier types what is in the drawer.
        closeShiftForm.actual_cash_balance = '';
        closeShiftForm.notes = '';
        showCloseShiftModal.value = true;
        // The expected cash is the server's figure for THIS shift, never the day's journal total.
        closeFigures.load();
    };

    const submitCloseShift = async () => {
        if (!activeShift.value || closeFigures.isLoading.value || closeFigures.failed.value) return;
        if (!isValidCountedCash(closeShiftForm.actual_cash_balance)) return;
        const shiftIdBeforeClose = closeFigures.shiftId.value ?? activeShift.value.id;
        isSubmitting.value = true;
        let closedShiftId;
        try {
            const response = await api.post('/shifts/close', {
                shift_id: shiftIdBeforeClose,
                actual_cash_balance: normalize(closeShiftForm.actual_cash_balance),
                notes: closeShiftForm.notes,
            });
            closedShiftId = response.data?.data?.id ?? shiftIdBeforeClose;
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

        // The close succeeded: show the final Z report; a failed report fetch is handled inside the dialog.
        lastClosedShiftId = closedShiftId;
        zPrint.presentClosedShift(closedShiftId);
        await fetchDailyJournal();
    };

    const retryClosedReport = () => zPrint.presentClosedShift(lastClosedShiftId);
    const printClosedReport = () => zPrint.printReport(zPrint.closedReport.value);

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

    const printActiveZReport = () => zPrint.printShiftZReport(activeShift.value?.id);

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
        zReport: zPrint.zReport,
        closeFigures: reactive(closeFigures),
        closedResult: reactive(zPrint),
        retryClosedReport,
        printClosedReport,
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
