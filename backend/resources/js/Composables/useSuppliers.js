import { ref, reactive, onMounted } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { useTrans } from './useTrans';

export function useSuppliers() {
    const { t } = useTrans();

    const suppliers = ref([]);
    const metrics = ref({
        total_payable: 0,
        creditors_count: 0,
        total_suppliers: 0,
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

    // Supplier Form Modal
    const showSupplierModal = ref(false);
    const editingSupplier = ref(null);
    const form = reactive({
        name: '',
        company_name: '',
        phone: '',
        address: '',
        opening_balance: '0.000',
        notes: '',
    });

    // Payment Modal
    const showPaymentModal = ref(false);
    const targetSupplier = ref(null);
    const isSubmittingPayment = ref(false);
    const paymentForm = reactive({
        amount: '',
        payment_method: 'cash',
        payment_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const fetchSuppliers = async (page = 1) => {
        isLoading.value = true;
        try {
            const response = await api.get('/suppliers', {
                params: {
                    search: searchQuery.value || undefined,
                    balance_type: debtStatus.value !== 'all' ? debtStatus.value : undefined,
                    page: page,
                    per_page: 15,
                },
            });
            suppliers.value = response.data?.data || [];
            metrics.value = response.data?.metrics || {
                total_payable: 0,
                creditors_count: 0,
                total_suppliers: 0,
            };
            pagination.value = response.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: 15,
                total: suppliers.value.length,
            };
        } catch (error) {
            console.error('Failed to load suppliers:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const debounceSearch = () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            fetchSuppliers(1);
        }, 300);
    };

    const setDebtStatus = (status) => {
        debtStatus.value = status;
        fetchSuppliers(1);
    };

    const openCreateModal = () => {
        editingSupplier.value = null;
        Object.assign(form, {
            name: '',
            company_name: '',
            phone: '',
            address: '',
            opening_balance: '0.000',
            notes: '',
        });
        showSupplierModal.value = true;
    };

    const openEditModal = (supplier) => {
        editingSupplier.value = supplier;
        Object.assign(form, {
            name: supplier.name,
            company_name: supplier.company_name || '',
            phone: supplier.phone || '',
            address: supplier.address || '',
            opening_balance: '0.000',
            notes: supplier.notes || '',
        });
        showSupplierModal.value = true;
    };

    const updateFormField = (field, val) => {
        form[field] = val;
    };

    const updatePaymentField = (field, val) => {
        paymentForm[field] = val;
    };

    const saveSupplier = async () => {
        isSubmitting.value = true;
        try {
            if (editingSupplier.value) {
                await api.put(`/suppliers/${editingSupplier.value.id}`, form);
                Swal.fire({ icon: 'success', title: t('common.success'), text: t('contacts.supplier_updated'), timer: 1500, showConfirmButton: false });
            } else {
                await api.post('/suppliers', form);
                Swal.fire({ icon: 'success', title: t('common.success'), text: t('contacts.supplier_added'), timer: 1500, showConfirmButton: false });
            }
            showSupplierModal.value = false;
            await fetchSuppliers(pagination.value.current_page);
        } catch (error) {
            Swal.fire({ icon: 'error', title: t('common.error'), text: error.userMessage || t('common.error') });
        } finally {
            isSubmitting.value = false;
        }
    };

    const openPaymentModal = (supplier) => {
        targetSupplier.value = supplier;
        paymentForm.amount = supplier.current_balance > 0 ? supplier.current_balance : '';
        paymentForm.payment_method = 'cash';
        paymentForm.payment_date = new Date().toISOString().split('T')[0];
        paymentForm.notes = '';
        showPaymentModal.value = true;
    };

    const savePayment = async () => {
        if (!paymentForm.amount || parseFloat(paymentForm.amount) <= 0) return;

        isSubmittingPayment.value = true;
        try {
            await api.post(`/suppliers/${targetSupplier.value.id}/payments`, paymentForm);
            Swal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('contacts.supplier_payment_recorded'),
                timer: 1500,
                showConfirmButton: false,
            });
            showPaymentModal.value = false;
            await fetchSuppliers(pagination.value.current_page);
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

    const deleteSupplier = async (supplier) => {
        const result = await Swal.fire({
            title: t('contacts.delete_supplier_confirm_title', { name: supplier.name }),
            text: t('contacts.delete_supplier_confirm_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
            confirmButtonColor: '#f43f5e',
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/suppliers/${supplier.id}`);
                Swal.fire({ icon: 'success', title: t('common.success'), text: t('contacts.supplier_deleted'), timer: 1500, showConfirmButton: false });
                await fetchSuppliers(pagination.value.current_page);
            } catch (error) {
                Swal.fire({ icon: 'error', title: t('common.error'), text: error.userMessage || t('contacts.cannot_delete_has_balance') });
            }
        }
    };

    onMounted(() => {
        fetchSuppliers(1);
    });

    return {
        suppliers,
        metrics,
        searchQuery,
        debtStatus,
        isLoading,
        isSubmitting,
        pagination,
        showSupplierModal,
        editingSupplier,
        form,
        showPaymentModal,
        targetSupplier,
        paymentForm,
        isSubmittingPayment,
        fetchSuppliers,
        debounceSearch,
        setDebtStatus,
        openCreateModal,
        openEditModal,
        updateFormField,
        updatePaymentField,
        saveSupplier,
        openPaymentModal,
        savePayment,
        deleteSupplier,
    };
}
