import { ref, reactive, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useCustomers() {
    const { t } = useTrans();

    const customers = ref([]);
    const metrics = ref({
        total_debt: 0,
        debtors_count: 0,
        total_customers: 0,
    });

    const searchQuery = ref('');
    const debtStatus = ref('all');
    const isLoading = ref(false);
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
        opening_balance: '0.000',
        notes: '',
    });

    // Payment Modal
    const showPaymentModal = ref(false);
    const targetCustomer = ref(null);
    const isSubmittingPayment = ref(false);
    const paymentForm = reactive({
        amount: '',
        payment_method: 'cash',
        payment_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const fetchCustomers = async (page = 1) => {
        isLoading.value = true;
        try {
            const response = await api.get('/customers', {
                params: {
                    search: searchQuery.value || undefined,
                    balance_type: debtStatus.value !== 'all' ? debtStatus.value : undefined,
                    page: page,
                    per_page: 15,
                },
            });
            customers.value = response.data?.data || [];
            metrics.value = response.data?.metrics || {
                total_debt: 0,
                debtors_count: 0,
                total_customers: 0,
            };
            pagination.value = response.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: 15,
                total: customers.value.length,
            };
        } catch (error) {
            console.error('Failed to load customers:', error);
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
            opening_balance: '0.000',
            notes: '',
        });
        showCustomerModal.value = true;
    };

    const openEditModal = (customer) => {
        editingCustomer.value = customer;
        Object.assign(form, {
            name: customer.name,
            phone: customer.phone || '',
            address: customer.address || '',
            tax_number: customer.tax_number || '',
            opening_balance: '0.000',
            notes: customer.notes || '',
        });
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
        try {
            if (editingCustomer.value) {
                await api.put(`/customers/${editingCustomer.value.id}`, form);
                Swal.fire({ icon: 'success', title: t('common.success'), text: t('contacts.customer_updated'), timer: 1500, showConfirmButton: false });
            } else {
                await api.post('/customers', form);
                Swal.fire({ icon: 'success', title: t('common.success'), text: t('contacts.customer_added'), timer: 1500, showConfirmButton: false });
            }
            showCustomerModal.value = false;
            await fetchCustomers(pagination.value.current_page);
        } catch (error) {
            Swal.fire({ icon: 'error', title: t('common.error'), text: error.userMessage || t('common.error') });
        } finally {
            isSubmitting.value = false;
        }
    };

    const openPaymentModal = (customer) => {
        targetCustomer.value = customer;
        paymentForm.amount = customer.current_balance > 0 ? customer.current_balance : '';
        paymentForm.payment_method = 'cash';
        paymentForm.payment_date = new Date().toISOString().split('T')[0];
        paymentForm.notes = '';
        showPaymentModal.value = true;
    };

    const savePayment = async () => {
        if (!paymentForm.amount || parseFloat(paymentForm.amount) <= 0) return;

        isSubmittingPayment.value = true;
        try {
            await api.post(`/customers/${targetCustomer.value.id}/payments`, paymentForm);
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('contacts.payment_recorded'),
                timer: 1500,
                showConfirmButton: false,
            });
            showPaymentModal.value = false;
            await fetchCustomers(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: t('common.error'),
                text: error.userMessage || t('common.error'),
            });
        } finally {
            isSubmittingPayment.value = false;
        }
    };

    const deleteCustomer = async (customer) => {
        const result = await Swal.fire({
            title: t('contacts.delete_customer_confirm_title', { name: customer.name }),
            text: t('contacts.delete_customer_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
            confirmButtonColor: '#f43f5e',
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/customers/${customer.id}`);
                Swal.fire({ icon: 'success', title: t('common.success'), text: t('contacts.customer_deleted'), timer: 1500, showConfirmButton: false });
                await fetchCustomers(pagination.value.current_page);
            } catch (error) {
                Swal.fire({ icon: 'error', title: t('common.error'), text: error.userMessage || t('contacts.cannot_delete_has_balance') });
            }
        }
    };

    onMounted(() => {
        fetchCustomers(1);
    });

    return {
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
    };
}
