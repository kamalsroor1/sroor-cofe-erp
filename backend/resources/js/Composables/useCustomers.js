import { ref, reactive, computed, onMounted } from 'vue';
import api from '../Services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { businessToday } from './useBusinessDate';

export function useCustomers() {
    const { t } = useTrans();
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();

    // ROLE-01: deleting a customer is its own permission (the server enforces it too).
    const canDeleteCustomer = computed(() => authStore.hasPermission('customers.delete'));

    const customers = ref([]);
    const metrics = ref({});

    const searchQuery = ref('');
    const debtStatus = ref('all');
    const isLoading = ref(false);
    const error = ref(false);
    const errorMessage = ref('');
    const isSubmitting = ref(false);

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    let debounceTimeout = null;

    // Customer Form Modal
    const showCustomerModal = ref(false);
    const editingCustomer = ref(null);
    const form = reactive({
        name: '',
        phone: '',
        address: '',
        tax_number: '',
        price_tier: 'retail',
        opening_balance: '0.000',
        // SHOP-03: empty = no limit.
        credit_limit: '',
        notes: '',
    });
    const formErrors = ref({});

    /** SHOP-03: a stored / default limit as the input shows it ('' = no limit). */
    const creditLimitInput = (value) => (value === null || value === undefined ? '' : String(value));

    // Payment Modal
    const showPaymentModal = ref(false);
    const targetCustomer = ref(null);
    const isSubmittingPayment = ref(false);
    const paymentForm = reactive({
        amount: '',
        payment_method: 'cash',
        payment_date: businessToday(),
        notes: '',
    });

    const fetchCustomers = async (page = 1) => {
        isLoading.value = true;
        error.value = false;
        errorMessage.value = '';
        try {
            const response = await api.get('/customers', {
                params: {
                    search: searchQuery.value || undefined,
                    debt_status: debtStatus.value !== 'all' ? debtStatus.value : undefined,
                    page: page,
                    per_page: 15,
                },
            });
            customers.value = response.data?.data || [];
            // ROLE-01: without reports.view the server omits total_debt / debtors_count.
            metrics.value = response.data?.summary || response.data?.metrics || {};
            pagination.value = response.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: 15,
                total: customers.value.length,
            };
        } catch (err) {
            error.value = true;
            errorMessage.value = err.userMessage || err.message || t('common.error_occurred');
            console.error('Failed to load customers:', err);
        } finally {
            isLoading.value = false;
        }
    };

    const debounceSearch = () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            fetchCustomers(1);
        }, 300);
    };

    const setDebtStatus = (status) => {
        debtStatus.value = status;
        fetchCustomers(1);
    };

    const openCreateModal = () => {
        editingCustomer.value = null;
        Object.assign(form, {
            name: '',
            phone: '',
            address: '',
            tax_number: '',
            price_tier: 'retail',
            opening_balance: '0.000',
            // SHOP-03: pre-filled with the shop default (settings); the user may change or clear it.
            credit_limit: creditLimitInput(appConfigStore.system?.customers_default_credit_limit),
            notes: '',
        });
        formErrors.value = {};
        showCustomerModal.value = true;
    };

    const openEditModal = (customer) => {
        editingCustomer.value = customer;
        Object.assign(form, {
            name: customer.name,
            phone: customer.phone || '',
            address: customer.address || '',
            tax_number: customer.tax_number || '',
            price_tier: customer.price_tier || 'retail',
            opening_balance: '0.000',
            credit_limit: creditLimitInput(customer.credit_limit),
            notes: customer.notes || '',
        });
        formErrors.value = {};
        showCustomerModal.value = true;
    };

    const updateFormField = (field, val) => {
        form[field] = val;
    };

    const updatePaymentField = (field, val) => {
        paymentForm[field] = val;
    };

    const saveCustomer = async () => {
        isSubmitting.value = true;
        formErrors.value = {};
        try {
            if (editingCustomer.value) {
                await api.put(`/customers/${editingCustomer.value.id}`, form);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('contacts.customer_updated'),
                    timer: 1500,
                    showConfirmButton: false,
                });
            } else {
                await api.post('/customers', form);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('contacts.customer_added'),
                    timer: 1500,
                    showConfirmButton: false,
                });
            }
            showCustomerModal.value = false;
            await fetchCustomers(pagination.value.current_page);
        } catch (err) {
            formErrors.value = err.response?.status === 422 ? (err.response.data?.errors ?? {}) : {};
            Swal.fire({ icon: 'error', title: t('common.error'), text: err.userMessage || t('common.error') });
        } finally {
            isSubmitting.value = false;
        }
    };

    const openPaymentModal = (customer) => {
        targetCustomer.value = customer;
        paymentForm.amount = customer.current_balance > 0 ? customer.current_balance : '';
        paymentForm.payment_method = 'cash';
        paymentForm.payment_date = businessToday();
        paymentForm.notes = '';
        showPaymentModal.value = true;
    };

    const savePayment = async () => {
        if (!paymentForm.amount || parseFloat(paymentForm.amount) <= 0) return;

        isSubmittingPayment.value = true;
        try {
            await api.post(`/customers/${targetCustomer.value.id}/collect-payment`, paymentForm);
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('contacts.payment_recorded'),
                timer: 1500,
                showConfirmButton: false,
            });
            showPaymentModal.value = false;
            await fetchCustomers(pagination.value.current_page);
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: err.userMessage || t('common.error'),
            });
        } finally {
            isSubmittingPayment.value = false;
        }
    };

    const deleteCustomer = async (customer) => {
        if (!canDeleteCustomer.value) return;
        const result = await Swal.fire({
            title: t('contacts.delete_customer_confirm_title', { name: customer.name }),
            text: t('contacts.delete_customer_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
            confirmButtonColor: 'var(--color-danger-solid)',
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/customers/${customer.id}`);
                Swal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('contacts.customer_deleted'),
                    timer: 1500,
                    showConfirmButton: false,
                });
                await fetchCustomers(pagination.value.current_page);
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: t('common.error'),
                    text: err.response?.data?.message || err.userMessage || t('contacts.cannot_delete_has_balance'),
                });
            }
        }
    };

    onMounted(() => {
        fetchCustomers(1);
    });

    return {
        error,
        errorMessage,
        customers,
        metrics,
        searchQuery,
        debtStatus,
        isLoading,
        isSubmitting,
        pagination,
        showCustomerModal,
        editingCustomer,
        form,
        formErrors,
        showPaymentModal,
        targetCustomer,
        paymentForm,
        isSubmittingPayment,
        fetchCustomers,
        debounceSearch,
        setDebtStatus,
        openCreateModal,
        openEditModal,
        updateFormField,
        updatePaymentField,
        saveCustomer,
        openPaymentModal,
        savePayment,
        deleteCustomer,
        canDeleteCustomer,
    };
}
