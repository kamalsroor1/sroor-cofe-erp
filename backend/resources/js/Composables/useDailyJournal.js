import { ref, reactive, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useDailyJournal() {
    const { t } = useTrans();

    const selectedDate = ref(new Date().toISOString().split('T')[0]);
    const activeTab = ref('invoices');
    const isLoading = ref(false);
    const isSubmitting = ref(false);

    const activeShift = ref(null);
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
        expected_cash_in_drawer: 0,
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
        try {
            const response = await api.get('/daily-journal', {
                params: {
                    date: selectedDate.value,
                },
            });
            const data = response.data?.data;
            if (data) {
                activeShift.value = data.active_shift;
                summary.value = data.summary || {};
                invoices.value = data.invoices || [];
                expenses.value = data.expenses || [];
            }
        } catch (error) {
            console.error('Failed to load daily journal:', error);
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
        closeShiftForm.actual_cash_balance = (summary.value?.expected_cash_in_drawer || 0).toString();
        closeShiftForm.notes = '';
        showCloseShiftModal.value = true;
    };

    const submitCloseShift = async () => {
        if (!activeShift.value) return;
        isSubmitting.value = true;
        try {
            const response = await api.post('/shifts/close', {
                shift_id: activeShift.value.id,
                actual_cash_balance: closeShiftForm.actual_cash_balance,
                notes: closeShiftForm.notes,
            });
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: response.data?.message || t('treasury.shift_closed_success'),
            });
            showCloseShiftModal.value = false;
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

    const submitExpense = async () => {
        isSubmitting.value = true;
        try {
            await api.post('/expenses', {
                ...expenseForm,
                category: 'مصاريف يومية',
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

    const printActiveZReport = async () => {
        if (!activeShift.value) return;
        try {
            const response = await api.get(`/shifts/${activeShift.value.id}/z-report`);
            const report = response.data?.report;
            if (report) {
                window.print();
            }
        } catch (error) {
            console.error('Failed to get Z-report:', error);
        }
    };

    onMounted(() => {
        fetchDailyJournal();
    });

    return {
        selectedDate,
        activeTab,
        isLoading,
        isSubmitting,
        activeShift,
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
