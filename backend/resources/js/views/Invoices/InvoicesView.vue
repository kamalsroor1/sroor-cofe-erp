<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal text-slate-900 dark:text-slate-100">
    <!-- Header with Filter toggle, Actions dropdown, and Fast POS -->
    <PageHeader :title="$t('invoices.title')" :subtitle="$t('invoices.subtitle')" :icon="'🛒'">
      <template #actions>
        <div class="flex items-center gap-2 flex-wrap">
          <BaseButton
            :active="isFilterSidebarOpen || activeFiltersCount > 0"
            :icon="SlidersHorizontal"
            :icon-class="isFilterSidebarOpen || activeFiltersCount > 0 ? 'text-theme-primary' : 'text-slate-500'"
            :label="$t('invoices.filter_search')"
            :badge="activeFiltersCount > 0 ? activeFiltersCount : null"
            @click="isFilterSidebarOpen = !isFilterSidebarOpen"
          />

          <BaseDropdown
            :label="$t('invoices.options_and_actions')"
            :icon="FileSpreadsheet"
            icon-class="text-emerald-500"
            align="end"
            :items="[
              { label: $t('invoices.export_excel_csv'), icon: Download, iconColor: 'text-emerald-500', onClick: exportToExcel },
              { label: $t('invoices.print_filtered_report'), icon: Printer, iconColor: 'text-cyan-500', onClick: printReport },
              { label: $t('invoices.refresh_now'), icon: RefreshCw, iconColor: 'text-theme-primary', onClick: () => fetchInvoices(pagination.current_page) }
            ]"
          />

          <BaseButton
            to="/pos"
            variant="gradient"
            :icon="Zap"
            :label="$t('invoices.pos_fast_badge')"
          />
        </div>
      </template>
    </PageHeader>

    <!-- Financial Metrics Cards -->
    <InvoicesMetricsCards :summary="summary" :is-loading="isLoading" />

    <!-- Workspace: Search + Table + Sidebar -->
    <div class="flex flex-col lg:flex-row gap-5 items-start">
      <div class="flex-1 w-full space-y-4 min-w-0">
        <InvoicesQuickSearch v-model="searchQuery" :active-preset="activeDatePreset" :presets="datePresets" @select-preset="applyDatePreset" @update:model-value="debounceSearch" />
        <InvoicesBulkActionsBar :selected-count="selectedInvoiceIds.length" @bulk-print="bulkPrintReceipts" @bulk-export="bulkExportSelected" @bulk-cancel="bulkCancelSelected" @deselect-all="selectedInvoiceIds = []" />
        <InvoicesTable :invoices="invoices" :is-loading="isLoading" :selected-ids="selectedInvoiceIds" :is-all-selected="isAllSelected" :pagination="pagination" @toggle-select="toggleSelectInvoice" @toggle-select-all="toggleSelectAll" @preview="openDetailsModal" @print="openPrintReceipt" @cancel="cancelInvoice" @change-page="fetchInvoices" @reset-filters="resetAllFilters" />
      </div>

      <InvoicesFilterSidebar :is-open="isFilterSidebarOpen" v-model:store-id="selectedStoreId" v-model:payment-type="selectedPaymentType" v-model:status="selectedStatus" v-model:date-from="dateFrom" v-model:date-to="dateTo" :store-options="storeOptions" :payment-type-options="paymentTypeOptions" :status-options="statusOptions" @close="isFilterSidebarOpen = false" @apply="fetchInvoices(1)" @reset="resetAllFilters" />
    </div>

    <!-- Invoice Details Modal -->
    <InvoiceDetailsModal :show="showDetailsModal" :invoice="selectedInvoiceDetails" :whats-app="whatsAppData" @close="showDetailsModal = false" @print="openPrintReceipt" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { SlidersHorizontal, FileSpreadsheet, Download, Printer, RefreshCw, Zap } from 'lucide-vue-next';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import BaseDropdown from '../../Components/Common/BaseDropdown.vue';
import InvoicesMetricsCards from '../../Components/Invoices/InvoicesMetricsCards.vue';
import InvoicesQuickSearch from '../../Components/Invoices/InvoicesQuickSearch.vue';
import InvoicesBulkActionsBar from '../../Components/Invoices/InvoicesBulkActionsBar.vue';
import InvoicesFilterSidebar from '../../Components/Invoices/InvoicesFilterSidebar.vue';
import InvoicesTable from '../../Components/Invoices/InvoicesTable.vue';
import InvoiceDetailsModal from '../../Components/Invoices/InvoiceDetailsModal.vue';

const invoices = ref([]);
const isFilterSidebarOpen = ref(false);
const selectedInvoiceIds = ref([]);
const activeDatePreset = ref('all');
const summary = ref({ total_sales: 0, total_paid: 0, total_due: 0, total_count: 0 });
const searchQuery = ref('');
const selectedStoreId = ref('all');
const selectedPaymentType = ref('all');
const selectedStatus = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(true);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const showDetailsModal = ref(false);
const selectedInvoiceDetails = ref(null);
const whatsAppData = ref(null);
let debounceTimer = null;

const paymentTypeOptions = computed(() => [
  { value: 'all', label: trans('invoices.all_payment_types') || 'كافة أنواع السداد' },
  { value: 'cash', label: trans('invoices.payment_cash_option') || 'نقدي (كاش)' },
  { value: 'credit', label: trans('invoices.payment_credit_option') || 'آجل (مديونية)' },
  { value: 'partial', label: trans('invoices.payment_partial_option') || 'سداد جزئي' }
]);
const statusOptions = computed(() => [
  { value: 'all', label: trans('invoices.status_all') || 'كافة الحالات' },
  { value: 'confirmed', label: trans('invoices.status_confirmed_option') || 'معتمدة ومسجلة' },
  { value: 'cancelled', label: trans('invoices.status_cancelled_option') || 'ملغاة ومعكوسة' }
]);
const storeOptions = computed(() => [
  { value: 'all', label: trans('invoices.all_stores') || 'كافة الفروع والمخازن' },
  { value: '1', label: trans('invoices.main_branch') || 'الفرع الرئيسي' }
]);
const datePresets = [
  { id: 'all', label: trans('invoices.date_preset_all') || 'الكل' },
  { id: 'today', label: trans('invoices.date_preset_today') || 'اليوم' },
  { id: 'yesterday', label: trans('invoices.date_preset_yesterday') || 'أمس' },
  { id: 'week', label: trans('invoices.date_preset_week') || 'آخر 7 أيام' },
  { id: 'month', label: trans('invoices.date_preset_month') || 'هذا الشهر' }
];

const activeFiltersCount = computed(() => {
  let count = 0;
  if (selectedPaymentType.value !== 'all') count++;
  if (selectedStatus.value !== 'all') count++;
  if (selectedStoreId.value !== 'all') count++;
  if (dateFrom.value || dateTo.value) count++;
  return count;
});

const isAllSelected = computed(() => invoices.value.length > 0 && selectedInvoiceIds.value.length === invoices.value.length);

const fetchInvoices = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/invoices', {
      params: {
        search: searchQuery.value || undefined,
        store_id: selectedStoreId.value !== 'all' ? selectedStoreId.value : undefined,
        payment_type: selectedPaymentType.value !== 'all' ? selectedPaymentType.value : undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        from_date: dateFrom.value || undefined,
        to_date: dateTo.value || undefined,
        page,
        per_page: 15,
      },
    });
    invoices.value = res.data?.data || [];
    summary.value = res.data?.summary || { total_sales: 0, total_paid: 0, total_due: 0, total_count: 0 };
    pagination.value = res.data?.meta || { current_page: page, last_page: 1, per_page: 15, total: invoices.value.length };
  } catch (e) {
    console.error('Failed to load invoices:', e);
  } finally {
    isLoading.value = false;
  }
};

const debounceSearch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchInvoices(1), 300);
};

const applyDatePreset = (presetId) => {
  activeDatePreset.value = presetId;
  const now = new Date();
  if (presetId === 'all') { dateFrom.value = ''; dateTo.value = ''; }
  else if (presetId === 'today') { const s = now.toISOString().split('T')[0]; dateFrom.value = s; dateTo.value = s; }
  else if (presetId === 'yesterday') { const y = new Date(); y.setDate(y.getDate() - 1); const s = y.toISOString().split('T')[0]; dateFrom.value = s; dateTo.value = s; }
  else if (presetId === 'week') { const w = new Date(); w.setDate(w.getDate() - 7); dateFrom.value = w.toISOString().split('T')[0]; dateTo.value = now.toISOString().split('T')[0]; }
  else if (presetId === 'month') { const m = new Date(now.getFullYear(), now.getMonth(), 1); dateFrom.value = m.toISOString().split('T')[0]; dateTo.value = now.toISOString().split('T')[0]; }
  fetchInvoices(1);
};

const resetAllFilters = () => {
  searchQuery.value = ''; selectedStoreId.value = 'all'; selectedPaymentType.value = 'all'; selectedStatus.value = 'all'; dateFrom.value = ''; dateTo.value = ''; activeDatePreset.value = 'all';
  fetchInvoices(1);
};

const toggleSelectInvoice = (id) => {
  const idx = selectedInvoiceIds.value.indexOf(id);
  if (idx > -1) selectedInvoiceIds.value.splice(idx, 1);
  else selectedInvoiceIds.value.push(id);
};

const toggleSelectAll = () => {
  selectedInvoiceIds.value = isAllSelected.value ? [] : invoices.value.map(i => i.id);
};

const openDetailsModal = async (inv) => {
  try {
    const res = await api.get(`/invoices/${inv.id}`);
    selectedInvoiceDetails.value = res.data?.data;
    whatsAppData.value = res.data?.whatsapp;
    showDetailsModal.value = true;
  } catch (e) {
    console.error('Failed to load invoice details:', e);
  }
};

const openPrintReceipt = (id) => {
  if (id) window.open(`/invoices/${id}/print`, '_blank', 'width=800,height=600');
};

const cancelInvoice = async (inv) => {
  const result = await Swal.fire({
    title: trans('invoices.cancel_invoice_confirm_title', { number: inv.invoice_number }) || `هل أنت متأكد من إلغاء الفاتورة ${inv.invoice_number}؟`,
    text: trans('invoices.cancel_invoice_confirm_text') || 'سيتم إرجاع كافة البضائع إلى رصيد المخزن وعكس أثرها المالي فوراً.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: trans('invoices.cancel_confirmed_btn') || 'نعم، قم بالإلغاء',
    cancelButtonText: trans('invoices.cancel_dismiss_btn') || 'تراجع',
    confirmButtonColor: '#f43f5e',
  });
  if (result.isConfirmed) {
    try {
      await api.post(`/invoices/${inv.id}/cancel`, { reason: trans('invoices.cancel_reason_default') || 'إلغاء من لوحة المبيعات' });
      Swal.fire({ icon: 'success', title: trans('common.success'), text: trans('invoices.invoice_cancelled_success') || 'تم إلغاء الفاتورة بنجاح', timer: 1500, showConfirmButton: false });
      await fetchInvoices(pagination.value.current_page);
    } catch (e) {
      Swal.fire({ icon: 'error', title: trans('common.error'), text: e.userMessage || trans('invoices.invoice_cancelled_failed') || 'فشل إلغاء الفاتورة' });
    }
  }
};

const bulkPrintReceipts = () => selectedInvoiceIds.value.forEach(id => window.open(`/invoices/${id}/print`, '_blank'));

const bulkExportSelected = () => {
  const selected = invoices.value.filter(inv => selectedInvoiceIds.value.includes(inv.id));
  let csv = "رقم الفاتورة,العميل,الهاتف,التاريخ,طريقة الدفع,الصافي,المدفوع,المتبقي,الحالة\n";
  selected.forEach(inv => { csv += `"${inv.invoice_number}","${inv.customer_name || ''}","${inv.customer_phone || ''}","${inv.invoice_date}","${inv.payment_type}","${inv.net_total}","${inv.paid_amount}","${inv.remaining_amount}","${inv.status}"\n`; });
  const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `invoices_export_${new Date().toISOString().split('T')[0]}.csv`;
  link.click();
};

const bulkCancelSelected = async () => {
  const count = selectedInvoiceIds.value.length;
  const res = await Swal.fire({
    title: trans('invoices.bulk_cancel_confirm_title', { count }) || `إلغاء ${count} فواتير محددة؟`,
    text: trans('invoices.bulk_cancel_confirm_text') || 'سيتم إلغاء كافة الفواتير المحددة وإرجاع بضائعها إلى رصيد المخزن وعكس القيود المالية.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: trans('invoices.bulk_cancel_confirm_btn') || 'نعم، قم بالإلغاء المجمع',
    cancelButtonText: trans('invoices.cancel_dismiss_btn') || 'تراجع',
    confirmButtonColor: '#f43f5e',
  });
  if (res.isConfirmed) {
    let successCount = 0;
    for (const id of selectedInvoiceIds.value) {
      try {
        await api.post(`/invoices/${id}/cancel`, { reason: 'إلغاء مجمع من لوحة المبيعات' });
        successCount++;
      } catch (e) {
        console.error(`Failed to cancel invoice ${id}:`, e);
      }
    }
    Swal.fire({ icon: 'success', title: trans('common.success'), text: trans('invoices.bulk_cancel_success', { count: successCount }) || `تم إلغاء ${successCount} فواتير بنجاح.` });
    selectedInvoiceIds.value = [];
    await fetchInvoices(pagination.value.current_page);
  }
};

const exportToExcel = () => {
  let csv = "رقم الفاتورة,العميل,الهاتف,التاريخ,طريقة الدفع,الصافي,المدفوع,المتبقي,الحالة\n";
  invoices.value.forEach(inv => { csv += `"${inv.invoice_number}","${inv.customer_name || ''}","${inv.customer_phone || ''}","${inv.invoice_date}","${inv.payment_type}","${inv.net_total}","${inv.paid_amount}","${inv.remaining_amount}","${inv.status}"\n`; });
  const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `all_invoices_${new Date().toISOString().split('T')[0]}.csv`;
  link.click();
};

const printReport = () => window.print();

onMounted(() => fetchInvoices(1));
</script>
