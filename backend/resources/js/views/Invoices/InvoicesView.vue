<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('invoices.title')"
        :subtitle="$t('invoices.subtitle')"
        :icon="'🛒'"
      >
        <template #actions>
          <div class="flex items-center gap-2">
            <!-- Open POS Button -->
            <router-link
              to="/pos"
              class="px-5 py-2.5 bg-theme-gradient text-white shadow-theme-primary rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-theme-primary"
            >
              <Zap class="w-4 h-4 fill-slate-950" />
              <span>{{ $t('invoices.pos_fast_badge') }}</span>
            </router-link>
          </div>
        </template>
      </PageHeader>

      <!-- Financial Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <!-- Total Sales -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('common.total_sales') }}</span>
            <TrendingUp class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="text-2xl font-black text-emerald-400 font-mono">
            {{ formatMoney(summary.total_sales || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
          <span class="text-[10px] text-slate-500">{{ $t('invoices.confirmed_sales_sub') }}</span>
        </div>

        <!-- Total Paid -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('invoices.collected_cash_electronic') }}</span>
            <CheckCircle2 class="w-4 h-4 text-cyan-400" />
          </div>
          <div class="text-2xl font-black text-cyan-400 font-mono">
            {{ formatMoney(summary.total_paid || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
          <span class="text-[10px] text-slate-500">{{ $t('invoices.inflows_in_drawer_sub') }}</span>
        </div>

        <!-- Total Due -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('invoices.remaining_credit_due') }}</span>
            <Clock class="w-4 h-4 text-rose-400" />
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ formatMoney(summary.total_due || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
          <span class="text-[10px] text-slate-500">{{ $t('invoices.debt_under_collection_sub') }}</span>
        </div>

        <!-- Invoices Count -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('invoices.invoices_count_label') }}</span>
            <FileText class="w-4 h-4 text-amber-400" />
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
            {{ summary.total_count || 0 }} <span class="text-xs text-slate-400">{{ $t('invoices.invoice_unit') }}</span>
          </div>
          <span class="text-[10px] text-slate-500">{{ $t('invoices.sales_log_sub') }}</span>
        </div>
      </div>

      <!-- Search & Filters Bar -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none"
            :placeholder="$t('invoices.search_invoices_field_placeholder')"
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Payment Type Filter -->
        <div class="w-full md:w-36">
          <select
            v-model="selectedPaymentType"
            @change="fetchInvoices(1)"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
            <option value="all">{{ $t('invoices.payment_type_all') }}</option>
            <option value="cash">{{ $t('invoices.payment_cash_option') }}</option>
            <option value="credit">{{ $t('invoices.payment_credit_option') }}</option>
            <option value="partial">{{ $t('invoices.payment_partial_option') }}</option>
          </select>
        </div>

        <!-- Status Filter -->
        <div class="w-full md:w-36">
          <select
            v-model="selectedStatus"
            @change="fetchInvoices(1)"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
            <option value="all">{{ $t('invoices.status_all') }}</option>
            <option value="confirmed">{{ $t('invoices.status_confirmed_option') }}</option>
            <option value="cancelled">{{ $t('invoices.status_cancelled_option') }}</option>
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="flex items-center gap-2">
          <input
            v-model="dateFrom"
            @change="fetchInvoices(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
          <span class="text-xs text-slate-500 font-bold">—</span>
          <input
            v-model="dateTo"
            @change="fetchInvoices(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
          >
        </div>
      </div>

      <!-- Invoices Table -->
      <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="invoices.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.customer') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('invoices.payment_method') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('common.total') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.paid') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.remaining') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr
                v-for="(inv, idx) in invoices"
                :key="inv.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-100 dark:hover:bg-slate-900/50 transition-colors"
                :class="inv.is_cancelled ? 'opacity-50 line-through bg-rose-500/5' : ''"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                  {{ inv.invoice_number }}
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900 dark:text-white font-tajawal">{{ inv.customer_name }}</div>
                  <div v-if="inv.customer_phone" class="text-[10px] text-slate-500 font-mono mt-0.5">
                    {{ inv.customer_phone }}
                  </div>
                </td>
                <td class="py-3.5 px-4 font-mono text-slate-300">
                  {{ inv.invoice_date }} <span class="text-[10px] text-slate-500">({{ inv.created_at }})</span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span class="px-2 py-0.5 rounded-md text-[10px] font-bold font-tajawal bg-slate-800 border border-slate-700 text-slate-300">
                    {{ formatPaymentType(inv.payment_type) }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-white text-sm">
                  {{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-400">
                  {{ formatMoney(inv.paid_amount) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold" :class="inv.remaining_amount > 0 ? 'text-rose-400' : 'text-slate-500'">
                  {{ formatMoney(inv.remaining_amount) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-center font-tajawal">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="!inv.is_cancelled ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
                  >
                    {{ !inv.is_cancelled ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Preview Details Button -->
                    <button
                      type="button"
                      @click="openDetailsModal(inv)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-100 dark:hover:bg-slate-100 dark:hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="$t('invoices.view_invoice_details_hint')"
                    >
                      <Eye class="w-4 h-4" />
                    </button>

                    <!-- Cancel Button -->
                    <button
                      v-if="!inv.is_cancelled"
                      type="button"
                      @click="cancelInvoice(inv)"
                      class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
                      :title="$t('invoices.cancel_and_reverse_hint')"
                    >
                      <Ban class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :title="$t('invoices.no_invoices_found')"
          :description="$t('invoices.no_invoices_description')"
          :icon="'🛒'"
        >
          <template #action>
            <router-link
              to="/pos"
              class="px-5 py-2.5 bg-emerald-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-theme-primary"
            >
              {{ $t('invoices.open_pos_btn') }}
            </router-link>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400">
            {{ $t('invoices.total_results_invoices', { count: pagination.total }) }}
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchInvoices(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer"
            >
              {{ $t('common.previous') }}
            </button>
            <span class="px-3 py-1.5 text-xs font-mono text-slate-300 font-bold">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchInvoices(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Invoice Details & WhatsApp Modal -->
      <AppModal
        :show="showDetailsModal"
        :title="$t('invoices.sales_invoice_title', { number: selectedInvoiceDetails?.invoice_number || '' })"
        @close="showDetailsModal = false"
      >
        <div v-if="selectedInvoiceDetails" class="space-y-4 font-tajawal text-xs">
          <!-- Top Info Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl">
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('invoices.customer') }}:</span>
              <span class="text-white font-bold">{{ selectedInvoiceDetails.customer_name }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('common.date') }}:</span>
              <span class="text-slate-200 font-mono">{{ selectedInvoiceDetails.invoice_date }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('invoices.branch_cashier') }}</span>
              <span class="text-slate-200">{{ selectedInvoiceDetails.store_name }} ({{ selectedInvoiceDetails.cashier_name }})</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('invoices.payment_method') }}:</span>
              <span class="font-bold text-amber-400">{{ formatPaymentType(selectedInvoiceDetails.payment_type) }}</span>
            </div>
          </div>

          <!-- Items Table -->
          <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900 text-slate-400 border-b border-slate-200 dark:border-slate-800">
                  <th class="p-2.5 text-start font-bold">{{ $t('invoices.item') }}</th>
                  <th class="p-2.5 text-end font-bold">{{ $t('invoices.quantity') }}</th>
                  <th class="p-2.5 text-end font-bold">{{ $t('invoices.sale_price') }}</th>
                  <th class="p-2.5 text-end font-bold">{{ $t('common.total') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/50">
                <tr v-for="it in selectedInvoiceDetails.items" :key="it.id">
                  <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ it.item_name }}</td>
                  <td class="p-2.5 text-end font-mono text-amber-400">{{ it.quantity }} {{ it.unit }}</td>
                  <td class="p-2.5 text-end font-mono text-slate-300">{{ formatMoney(it.unit_price) }} {{ $t('common.currency') }}</td>
                  <td class="p-2.5 text-end font-mono font-bold text-emerald-400">{{ formatMoney(it.total_price) }} {{ $t('common.currency') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Financial Breakdown -->
          <div class="p-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1.5 font-mono text-xs">
            <div class="flex justify-between text-slate-300 font-tajawal">
              <span>{{ $t('invoices.subtotal_before_discount') }}</span>
              <span class="font-mono">{{ formatMoney(selectedInvoiceDetails.subtotal) }} {{ $t('common.currency') }}</span>
            </div>
            <div v-if="selectedInvoiceDetails.discount_amount > 0" class="flex justify-between text-rose-400 font-tajawal">
              <span>{{ $t('invoices.discount_value') }}</span>
              <span class="font-mono">-{{ formatMoney(selectedInvoiceDetails.discount_amount) }} {{ $t('common.currency') }}</span>
            </div>
            <div class="flex justify-between text-base font-black text-white pt-2 border-t border-slate-200 dark:border-slate-800 font-tajawal">
              <span>{{ $t('invoices.net_invoice') }}</span>
              <span class="text-emerald-400 font-mono">{{ formatMoney(selectedInvoiceDetails.net_total) }} {{ $t('common.currency') }}</span>
            </div>
            <div class="flex justify-between text-xs font-bold text-slate-400 font-tajawal">
              <span>{{ $t('invoices.paid') }}:</span>
              <span class="text-cyan-400 font-mono">{{ formatMoney(selectedInvoiceDetails.paid_amount) }} {{ $t('common.currency') }}</span>
            </div>
            <div v-if="selectedInvoiceDetails.remaining_amount > 0" class="flex justify-between text-xs font-bold text-rose-400 font-tajawal">
              <span>{{ $t('invoices.remaining_due') }}</span>
              <span class="font-mono">{{ formatMoney(selectedInvoiceDetails.remaining_amount) }} {{ $t('common.currency') }}</span>
            </div>
          </div>

          <!-- Actions: WhatsApp Share & Thermal Print -->
          <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
            <a
              v-if="whatsAppData?.whatsapp_url"
              :href="whatsAppData.whatsapp_url"
              target="_blank"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs flex items-center gap-2 shadow-md"
            >
              <Share2 class="w-4 h-4" />
              <span>{{ $t('invoices.share_whatsapp_btn') }}</span>
            </a>

            <button
              type="button"
              @click="window.print()"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl font-bold text-xs flex items-center gap-1.5 cursor-pointer"
            >
              <Printer class="w-4 h-4 text-amber-400" />
              <span>{{ $t('invoices.print_receipt_btn') }}</span>
            </button>
          </div>
        </div>
      </AppModal>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';
import {
    Zap,
    Search,
    TrendingUp,
    CheckCircle2,
    Clock,
    FileText,
    Eye,
    Ban,
    Share2,
    Printer
} from 'lucide-vue-next';

const invoices = ref([]);
const summary = ref({
    total_sales: 0,
    total_paid: 0,
    total_due: 0,
    total_count: 0,
});

const searchQuery = ref('');
const selectedPaymentType = ref('all');
const selectedStatus = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

let debounceTimer = null;

const showDetailsModal = ref(false);
const selectedInvoiceDetails = ref(null);
const whatsAppData = ref(null);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatPaymentType = (type) => {
    const map = {
        cash: `💵 ${trans('invoices.cash')}`,
        credit: `📝 ${trans('invoices.credit')}`,
        partial: `⚖️ ${trans('invoices.partial')}`,
        bank_transfer: `⚡ ${trans('contacts.bank_transfer')}`,
    };
    return map[type] || type;
};

const fetchInvoices = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/invoices', {
            params: {
                search: searchQuery.value || undefined,
                payment_type: selectedPaymentType.value !== 'all' ? selectedPaymentType.value : undefined,
                status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
                from_date: dateFrom.value || undefined,
                to_date: dateTo.value || undefined,
                page: page,
                per_page: 15,
            },
        });
        invoices.value = response.data?.data || [];
        summary.value = response.data?.summary || {
            total_sales: 0,
            total_paid: 0,
            total_due: 0,
            total_count: 0,
        };
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 15,
            total: invoices.value.length,
        };
    } catch (error) {
        console.error('Failed to load invoices:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchInvoices(1);
    }, 300);
};

const openDetailsModal = async (inv) => {
    try {
        const response = await api.get(`/invoices/${inv.id}`);
        selectedInvoiceDetails.value = response.data?.data;
        whatsAppData.value = response.data?.whatsapp;
        showDetailsModal.value = true;
    } catch (error) {
        console.error('Failed to load invoice details:', error);
    }
};

const cancelInvoice = async (inv) => {
    const result = await Swal.fire({
        title: trans('invoices.cancel_invoice_confirm_title', { number: inv.invoice_number }),
        text: trans('invoices.cancel_invoice_confirm_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('invoices.cancel_confirmed_btn'),
        cancelButtonText: trans('invoices.cancel_dismiss_btn'),
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.post(`/invoices/${inv.id}/cancel`, { reason: trans('invoices.cancel_reason_default') });
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('invoices.invoice_cancelled_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchInvoices(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: error.userMessage || trans('invoices.invoice_cancelled_failed'),
            });
        }
    }
};

onMounted(() => {
    fetchInvoices(1);
});
</script>
