import { ref, reactive, computed, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useExpenses() {
    const { t } = useTrans();

    const expenses = ref([]);
    const metrics = ref({
        total_month: 0,
        total_cash: 0,
        total_filtered: 0,
    });

    const costCenters = ref({});
    const quickCategories = ref([]);

    const searchQuery = ref('');
    const selectedCostCenter = ref('all');
    const selectedCategory = ref('all');
    const dateFrom = ref('');
    const dateTo = ref('');
    const isLoading = ref(false);
    const isSubmitting = ref(false);

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });

    let debounceTimeout = null;

    // Add / Edit State
    const showExpenseModal = ref(false);
    const editingExpense = ref(null);
    const form = reactive({
        title: '',
        category: '',
        cost_center: 'operational',
        amount: '',
        expense_date: new Date().toISOString().split('T')[0],
        payment_method: 'cash',
        notes: '',
    });

    const costCenterFilterOptions = computed(() => [
        { value: 'all', label: t('expenses.all_cost_centers') },
        ...Object.entries(costCenters.value).map(([k, v]) => ({ value: k, label: v }))
    ]);

    const costCenterModalOptions = computed(() => [
        ...Object.entries(costCenters.value).map(([k, v]) => ({ value: k, label: v }))
    ]);

    const fetchExpenses = async (page = 1) => {
        isLoading.value = true;
        try {
            const response = await api.get('/expenses', {
                params: {
                    search: searchQuery.value || undefined,
                    cost_center: selectedCostCenter.value !== 'all' ? selectedCostCenter.value : undefined,
                    category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
                    from: dateFrom.value || undefined,
                    to: dateTo.value || undefined,
                    page: page,
                    per_page: 20,
                },
            });
            expenses.value = response.data?.data || [];
            metrics.value = response.data?.summary || {
                total_month: 0,
                total_cash: 0,
                total_filtered: 0,
            };
            costCenters.value = response.data?.cost_centers || {};
            quickCategories.value = response.data?.quick_categories || [];
            pagination.value = response.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: 20,
                total: expenses.value.length,
            };
        } catch (error) {
            console.error('Failed to load expenses:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const debounceSearch = () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            fetchExpenses(1);
        }, 300);
    };

    const filterByCategory = (cat) => {
        selectedCategory.value = cat;
        fetchExpenses(1);
    };

    const openCreateModal = () => {
        editingExpense.value = null;
        Object.assign(form, {
            title: '',
            category: t('expenses.preset_customs'),
            cost_center: 'operational',
            amount: '',
            expense_date: new Date().toISOString().split('T')[0],
            payment_method: 'cash',
            notes: '',
        });
        showExpenseModal.value = true;
    };

    const openEditModal = (e) => {
        editingExpense.value = e;
        Object.assign(form, {
            title: e.title,
            category: e.category,
            cost_center: e.cost_center || 'operational',
            amount: e.amount,
            expense_date: e.expense_date,
            payment_method: e.payment_method || 'cash',
            notes: e.notes || '',
        });
        showExpenseModal.value = true;
    };

    const updateFormField = (field, val) => {
        form[field] = val;
    };

    const saveExpense = async () => {
        isSubmitting.value = true;
        try {
            if (editingExpense.value) {
                await api.put(`/expenses/${editingExpense.value.id}`, form);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('expenses.expense_updated'),
                    timer: 1500,
                    showConfirmButton: false,
                });
            } else {
                await api.post('/expenses', form);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('expenses.expense_added'),
                    timer: 1500,
                    showConfirmButton: false,
                });
            }
            showExpenseModal.value = false;
            await fetchExpenses(pagination.value.current_page);
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

    const deleteExpense = async (e) => {
        const result = await Swal.fire({
            title: t('expenses.delete_expense_confirm_title', { title: e.title }),
            text: t('expenses.delete_expense_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.delete'),
            cancelButtonText: t('common.cancel'),
            confirmButtonColor: '#f43f5e',
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/expenses/${e.id}`);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('expenses.expense_deleted'),
                    timer: 1500,
                    showConfirmButton: false,
                });
                await fetchExpenses(pagination.value.current_page);
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: t('common.error'),
                    text: error.userMessage || t('common.error'),
                });
            }
        }
    };

    onMounted(() => {
        fetchExpenses(1);
    });

    return {
        expenses,
        metrics,
        costCenters,
        quickCategories,
        searchQuery,
        selectedCostCenter,
        selectedCategory,
        costCenterFilterOptions,
        costCenterModalOptions,
        dateFrom,
        dateTo,
        isLoading,
        isSubmitting,
        pagination,
        showExpenseModal,
        editingExpense,
        form,
        fetchExpenses,
        debounceSearch,
        filterByCategory,
        openCreateModal,
        openEditModal,
        updateFormField,
        saveExpense,
        deleteExpense,
    };
}
