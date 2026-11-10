import { ref, computed } from 'vue';
import api from '../Services/api';
import DarkSwal, { DANGER_CONFIRM_BUTTON_CLASS, notifyError, notifySuccess, notifyWarning } from '../helpers/alert';
import { trans } from '../helpers/trans';
import { useAuthStore } from '../stores/auth';
import { addDays, businessToday, startOfMonth } from './useBusinessDate';

const STORE_ACTIVE = 'active';
const STORE_ALL = 'all';
const PER_PAGE = 15;

export function useInvoices() {
    const authStore = useAuthStore();

    const invoices = ref([]);
    const summary = ref({ total_sales: 0, total_paid: 0, total_due: 0, total_count: 0 });
    const pagination = ref({ current_page: 1, last_page: 1, per_page: PER_PAGE, total: 0 });
    const isLoading = ref(true);
    const isExporting = ref(false);
    const error = ref(false);
    const errorMessage = ref('');

    const searchQuery = ref('');
    const selectedStoreId = ref(STORE_ACTIVE);
    const selectedPaymentType = ref('all');
    const selectedStatus = ref('all');
    const dateFrom = ref('');
    const dateTo = ref('');
    const activeDatePreset = ref('all');
    const selectedInvoiceIds = ref([]);

    const selectedInvoiceDetails = ref(null);
    const whatsAppData = ref(null);
    const showDetailsModal = ref(false);

    let debounceTimer = null;

    const canViewAllStores = computed(() => authStore.hasPermission(['stores.manage', 'stores.view_all']));

    const storeOptions = computed(() => [
        { value: STORE_ACTIVE, label: trans('invoices.active_store_option') },
        ...(canViewAllStores.value ? [{ value: STORE_ALL, label: trans('invoices.all_stores') }] : []),
        ...(authStore.stores || []).map((s) => ({ value: String(s.id), label: s.name })),
    ]);
    const paymentTypeOptions = computed(() => [
        { value: 'all', label: trans('invoices.all_payment_types') },
        { value: 'cash', label: trans('invoices.payment_cash_option') },
        { value: 'credit', label: trans('invoices.payment_credit_option') },
        { value: 'partial', label: trans('invoices.payment_partial_option') },
    ]);
    const statusOptions = computed(() => [
        { value: 'all', label: trans('invoices.status_all') },
        { value: 'confirmed', label: trans('invoices.status_confirmed_option') },
        { value: 'cancelled', label: trans('invoices.status_cancelled_option') },
    ]);
    const datePresets = computed(() => [
        { id: 'all', label: trans('invoices.date_preset_all') },
        { id: 'today', label: trans('invoices.date_preset_today') },
        { id: 'yesterday', label: trans('invoices.date_preset_yesterday') },
        { id: 'week', label: trans('invoices.date_preset_week') },
        { id: 'month', label: trans('invoices.date_preset_month') },
    ]);

    const activeFiltersCount = computed(() => {
        let count = 0;
        if (selectedPaymentType.value !== 'all') count++;
        if (selectedStatus.value !== 'all') count++;
        if (selectedStoreId.value !== STORE_ACTIVE) count++;
        if (dateFrom.value || dateTo.value) count++;
        return count;
    });

    const isAllSelected = computed(
        () => invoices.value.length > 0 && selectedInvoiceIds.value.length === invoices.value.length
    );

    const filterParams = () => ({
        search: searchQuery.value || undefined,
        store_id: selectedStoreId.value !== STORE_ACTIVE ? selectedStoreId.value : undefined,
        payment_type: selectedPaymentType.value !== 'all' ? selectedPaymentType.value : undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        from_date: dateFrom.value || undefined,
        to_date: dateTo.value || undefined,
    });

    const fetchInvoices = async (page = 1) => {
        isLoading.value = true;
        error.value = false;
        errorMessage.value = '';
        try {
            const res = await api.get('/invoices', { params: { ...filterParams(), page, per_page: PER_PAGE } });
            invoices.value = res.data?.data || [];
            summary.value = res.data?.summary || { total_sales: 0, total_paid: 0, total_due: 0, total_count: 0 };
            pagination.value = res.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: PER_PAGE,
                total: invoices.value.length,
            };
        } catch (err) {
            error.value = true;
            errorMessage.value = err.userMessage || err.message || trans('common.error_occurred');
        } finally {
            isLoading.value = false;
        }
    };

    const refreshCurrentPage = () => fetchInvoices(pagination.value?.current_page || 1);

    const debounceSearch = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchInvoices(1), 300);
    };

    const applyDatePreset = (presetId) => {
        activeDatePreset.value = presetId;
        const today = businessToday();
        if (presetId === 'all') {
            dateFrom.value = '';
            dateTo.value = '';
        } else if (presetId === 'today') {
            dateFrom.value = today;
            dateTo.value = today;
        } else if (presetId === 'yesterday') {
            dateFrom.value = addDays(today, -1);
            dateTo.value = dateFrom.value;
        } else if (presetId === 'week') {
            dateFrom.value = addDays(today, -7);
            dateTo.value = today;
        } else if (presetId === 'month') {
            dateFrom.value = startOfMonth(today);
            dateTo.value = today;
        }
        fetchInvoices(1);
    };

    const resetAllFilters = () => {
        searchQuery.value = '';
        selectedStoreId.value = STORE_ACTIVE;
        selectedPaymentType.value = 'all';
        selectedStatus.value = 'all';
        dateFrom.value = '';
        dateTo.value = '';
        activeDatePreset.value = 'all';
        fetchInvoices(1);
    };

    const toggleSelectInvoice = (id) => {
        const idx = selectedInvoiceIds.value.indexOf(id);
        if (idx > -1) selectedInvoiceIds.value.splice(idx, 1);
        else selectedInvoiceIds.value.push(id);
    };

    const toggleSelectAll = () => {
        selectedInvoiceIds.value = isAllSelected.value ? [] : invoices.value.map((i) => i.id);
    };

    const openDetailsModal = async (inv) => {
        try {
            const res = await api.get(`/invoices/${inv.id}`);
            selectedInvoiceDetails.value = res.data?.data;
            whatsAppData.value = res.data?.whatsapp;
            showDetailsModal.value = true;
        } catch {
            // api.js already shows the failure toast.
        }
    };

    const openPrintReceipt = (id) => {
        if (id) window.open(`/invoices/${id}/print`, '_blank', 'width=800,height=600');
    };

    const bulkPrintReceipts = () =>
        selectedInvoiceIds.value.forEach((id) => window.open(`/invoices/${id}/print`, '_blank'));

    /** Confirm dialog with a mandatory reason. Resolves to the trimmed reason, or null when dismissed. */
    const askCancelReason = async ({ title, text, confirmButtonText }) => {
        const result = await DarkSwal.fire({
            title,
            text,
            icon: 'warning',
            input: 'textarea',
            inputPlaceholder: trans('invoices.cancel_reason_placeholder'),
            inputAttributes: { maxlength: 500, 'aria-label': trans('invoices.cancel_reason_label') },
            inputValidator: (value) => (value && value.trim() ? null : trans('invoices.cancel_reason_required')),
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText,
            cancelButtonText: trans('invoices.cancel_dismiss_btn'),
            customClass: { ...DarkSwal.params.customClass, confirmButton: DANGER_CONFIRM_BUTTON_CLASS },
        });
        return result.isConfirmed ? result.value.trim() : null;
    };

    const cancelInvoice = async (inv) => {
        const reason = await askCancelReason({
            title: trans('invoices.cancel_invoice_confirm_title', { number: inv.invoice_number }),
            text: trans('invoices.cancel_invoice_confirm_text'),
            confirmButtonText: trans('invoices.cancel_confirmed_btn'),
        });
        if (reason === null) return;
        try {
            await api.post(`/invoices/${inv.id}/cancel`, { reason });
            notifySuccess(trans('invoices.invoice_cancelled_success'));
            await refreshCurrentPage();
        } catch (e) {
            notifyError(trans('common.error'), e.userMessage || trans('invoices.invoice_cancelled_failed'));
        }
    };

    const bulkCancelSelected = async () => {
        const ids = [...selectedInvoiceIds.value];
        const count = ids.length;
        const reason = await askCancelReason({
            title: trans('invoices.bulk_cancel_confirm_title', { count }),
            text: trans('invoices.bulk_cancel_confirm_text'),
            confirmButtonText: trans('invoices.bulk_cancel_confirm_btn'),
        });
        if (reason === null) return;

        // Sequential on purpose: every cancel locks treasury/customer/stock rows.
        const results = [];
        for (const id of ids) {
            try {
                await api.post(`/invoices/${id}/cancel`, { reason });
                results.push('ok');
            } catch {
                results.push('failed');
            }
        }
        const success = results.filter((r) => r === 'ok').length;
        const failed = count - success;
        const params = { success, failed, total: count };

        if (failed === 0) notifySuccess(trans('invoices.bulk_cancel_result_success', params));
        else if (success === 0) notifyError(trans('invoices.bulk_cancel_result_failed', params));
        else notifyWarning(trans('invoices.bulk_cancel_result_partial', params));

        // Keep only the invoices that failed selected so the user can retry them.
        selectedInvoiceIds.value = ids.filter((_, i) => results[i] === 'failed');
        await refreshCurrentPage();
    };

    const downloadBlob = (blob, filename) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    /** Server-side CSV of every invoice matching the current filters, or only `ids` when given. */
    const exportInvoices = async (ids = null) => {
        if (isExporting.value) return;
        isExporting.value = true;
        try {
            const res = await api.get('/invoices/export', {
                params: ids ? { ids } : filterParams(),
                responseType: 'blob',
            });
            downloadBlob(res.data, `invoices_${businessToday()}.csv`);
        } catch (e) {
            // api.js already shows the dialog for 403.
            if (e?.response?.status !== 403) notifyError(trans('common.error'), trans('invoices.export_failed'));
        } finally {
            isExporting.value = false;
        }
    };

    const exportSelected = () => exportInvoices([...selectedInvoiceIds.value]);

    return {
        invoices,
        summary,
        pagination,
        isLoading,
        isExporting,
        error,
        errorMessage,
        searchQuery,
        selectedStoreId,
        selectedPaymentType,
        selectedStatus,
        dateFrom,
        dateTo,
        activeDatePreset,
        selectedInvoiceIds,
        selectedInvoiceDetails,
        whatsAppData,
        showDetailsModal,
        storeOptions,
        paymentTypeOptions,
        statusOptions,
        datePresets,
        activeFiltersCount,
        isAllSelected,
        fetchInvoices,
        refreshCurrentPage,
        debounceSearch,
        applyDatePreset,
        resetAllFilters,
        toggleSelectInvoice,
        toggleSelectAll,
        openDetailsModal,
        openPrintReceipt,
        bulkPrintReceipts,
        cancelInvoice,
        bulkCancelSelected,
        exportInvoices,
        exportSelected,
    };
}
