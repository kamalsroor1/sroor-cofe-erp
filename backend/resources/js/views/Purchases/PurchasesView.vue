<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('purchases.title')"
        :subtitle="$t('purchases.subtitle')"
        :icon="'🚛'"
      >
        <template #actions>
          <div class="flex items-center gap-2">
            <!-- Smart Reorder Link -->
            <router-link
              to="/purchases/smart-reorder"
              class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-purple-500/20"
            >
              <Sparkles class="w-4 h-4 text-amber-300" />
              <span>{{ $t('purchases.smart_reorder_radar') }}</span>
            </router-link>

            <!-- Create Purchase Link -->
            <router-link
              to="/purchases/create"
              class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-amber-500/20"
            >
              <Plus class="w-4 h-4" />
              <span>{{ $t('purchases.new_purchase') }}</span>
            </router-link>
          </div>
        </template>
      </PageHeader>

      <!-- Summary Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Purchases -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('purchases.total_purchases') }}</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <TrendingUp class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-emerald-400 font-mono">
            {{ formatMoney(metrics.total_purchases || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
          <div class="text-[11px] text-slate-500">
            {{ $t('purchases.total_purchases_sub') }}
          </div>
        </div>

        <!-- Unpaid Debt to Suppliers -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('purchases.unpaid_total') }}</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
              <Clock class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ formatMoney(metrics.unpaid_total || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
          </div>
          <div class="text-[11px] text-slate-500">
            {{ $t('purchases.unpaid_total_sub') }}
          </div>
        </div>

        <!-- Confirmed Purchases Count -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('purchases.confirmed_count') }}</span>
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
              <FileCheck class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-white font-mono">
            {{ metrics.confirmed_count || 0 }} <span class="text-xs text-slate-400">{{ $t('invoices.invoices_count_label') }}</span>
          </div>
          <div class="text-[11px] text-slate-500">
            {{ $t('purchases.confirmed_count_sub') }}
          </div>
        </div>
      </div>

      <!-- Filters & Search Bar -->
      <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none"
            :placeholder="$t('purchases.search_purchases_placeholder')"
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Status Filter -->
        <div class="w-full md:w-44">
          <select
            v-model="selectedStatus"
            @change="fetchPurchases(1)"
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option value="all">{{ $t('purchases.status_all') }}</option>
            <option value="confirmed">{{ $t('purchases.status_confirmed_badge') }}</option>
            <option value="cancelled">{{ $t('purchases.status_cancelled_badge') }}</option>
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="flex items-center gap-2">
          <input
            v-model="dateFrom"
            @change="fetchPurchases(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
          <span class="text-xs text-slate-500 font-bold">—</span>
          <input
            v-model="dateTo"
            @change="fetchPurchases(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
        </div>
      </div>

      <!-- Purchases Table -->
      <div class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <!-- Loading Spinner -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="purchases.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('invoices.invoice_number') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('purchases.supplier') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('common.total') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.paid') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('invoices.remaining_due') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
              <tr
                v-for="(p, idx) in purchases"
                :key="p.id"
                class="hover:bg-slate-900/50 transition-colors"
                :class="p.status === 'cancelled' ? 'opacity-50 line-through bg-rose-500/5' : ''"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                  {{ p.purchase_number }}
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-white font-tajawal text-sm">{{ p.supplier_name }}</div>
                  <div v-if="p.supplier_company" class="text-[10px] text-slate-400 font-tajawal mt-0.5">
                    {{ p.supplier_company }}
                  </div>
                </td>
                <td class="py-3.5 px-4 font-mono text-slate-300">
                  {{ p.purchase_date }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-white text-sm">
                  {{ formatMoney(p.net_total) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-400">
                  {{ formatMoney(p.paid_amount) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold" :class="p.remaining_amount > 0 ? 'text-rose-400' : 'text-slate-500'">
                  {{ formatMoney(p.remaining_amount) }} {{ $t('common.currency') }}
                </td>
                <td class="py-3.5 px-4 text-center font-tajawal">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="p.status === 'confirmed' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
                  >
                    {{ p.status === 'confirmed' ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Preview Button -->
                    <button
                      type="button"
                      @click="openDetailsModal(p)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="$t('purchases.view_items_hint')"
                    >
                      <Eye class="w-4 h-4" />
                    </button>

                    <!-- Cancel Invoice Button -->
                    <button
                      v-if="p.status === 'confirmed'"
                      type="button"
                      @click="cancelPurchase(p)"
                      class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
                      :title="$t('purchases.cancel_invoice_hint')"
                    >
                      <Ban class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <EmptyState
          v-else
          :title="$t('purchases.no_purchases_found')"
          :description="$t('purchases.no_purchases_description')"
          :icon="'🚛'"
        >
          <template #action>
            <router-link
              to="/purchases/create"
              class="px-5 py-2.5 bg-amber-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20"
            >
              {{ $t('purchases.add_first_purchase') }}
            </router-link>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400">
            {{ $t('purchases.total_results_purchases', { count: pagination.total }) }}
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchPurchases(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer"
            >
              {{ $t('common.previous') }}
            </button>
            <span class="px-3 py-1.5 text-xs font-mono text-slate-300 font-bold">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchPurchases(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Purchase Details Modal -->
      <AppModal
        :show="showDetailsModal"
        :title="$t('purchases.purchase_details_title', { number: selectedPurchase?.purchase_number })"
        @close="showDetailsModal = false"
      >
        <div v-if="selectedPurchase" class="space-y-4 font-tajawal text-xs">
          <!-- Summary Header -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-900/80 border border-slate-800 rounded-2xl">
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('purchases.supplier') }}:</span>
              <span class="text-white font-bold">{{ selectedPurchase.supplier_name }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('purchases.purchase_date') }}:</span>
              <span class="text-slate-200 font-mono">{{ selectedPurchase.purchase_date }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('purchases.received_branch') }}:</span>
              <span class="text-slate-200">{{ selectedPurchase.store_name || $t('common.main_branch') }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('common.status') }}:</span>
              <span class="font-bold" :class="selectedPurchase.status === 'confirmed' ? 'text-emerald-400' : 'text-rose-400'">
                {{ selectedPurchase.status === 'confirmed' ? $t('purchases.status_confirmed_badge') : $t('purchases.status_cancelled_badge') }}
              </span>
            </div>
          </div>

          <!-- Items Table -->
          <div class="border border-slate-800 rounded-xl overflow-hidden">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                  <th class="p-2.5 text-start font-bold">{{ $t('purchases.item_material') }}</th>
                  <th class="p-2.5 text-end font-bold">{{ $t('common.quantity') }}</th>
                  <th class="p-2.5 text-end font-bold">{{ $t('inventory.purchase_price') }}</th>
                  <th class="p-2.5 text-end font-bold">{{ $t('common.total') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/50">
                <tr v-for="item in selectedPurchase.items" :key="item.id">
                  <td class="p-2.5 font-bold text-white">{{ item.item_name }}</td>
                  <td class="p-2.5 text-end font-mono text-amber-400">{{ item.quantity }} {{ item.unit }}</td>
                  <td class="p-2.5 text-end font-mono text-slate-300">{{ formatMoney(item.cost_price) }} {{ $t('common.currency') }}</td>
                  <td class="p-2.5 text-end font-mono font-bold text-emerald-400">{{ formatMoney(item.total_price) }} {{ $t('common.currency') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Financial Breakdown -->
          <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-1.5 font-mono text-xs">
            <div class="flex justify-between text-slate-300 font-tajawal">
              <span>{{ $t('purchases.items_subtotal') }}</span>
              <span class="font-mono">{{ formatMoney(selectedPurchase.subtotal) }} {{ $t('common.currency') }}</span>
            </div>
            <div v-if="selectedPurchase.discount_amount > 0" class="flex justify-between text-rose-400 font-tajawal">
              <span>{{ $t('purchases.discount_earned') }}</span>
              <span class="font-mono">-{{ formatMoney(selectedPurchase.discount_amount) }} {{ $t('common.currency') }}</span>
            </div>
            <div v-if="selectedPurchase.additional_expenses_total > 0" class="flex justify-between text-amber-400 font-tajawal">
              <span>{{ $t('purchases.additional_expenses_loaded') }}</span>
              <span class="font-mono">+{{ formatMoney(selectedPurchase.additional_expenses_total) }} {{ $t('common.currency') }}</span>
            </div>
            <div class="flex justify-between text-base font-black text-white pt-2 border-t border-slate-800 font-tajawal">
              <span>{{ $t('invoices.net_invoice') }}</span>
              <span class="text-emerald-400 font-mono">{{ formatMoney(selectedPurchase.net_total) }} {{ $t('common.currency') }}</span>
            </div>
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
    Plus,
    Search,
    TrendingUp,
    Clock,
    FileCheck,
    Eye,
    Ban,
    Sparkles
} from 'lucide-vue-next';

const purchases = ref([]);
const metrics = ref({
    total_purchases: 0,
    unpaid_total: 0,
    confirmed_count: 0,
});

const searchQuery = ref('');
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

let debounceTimeout = null;

const showDetailsModal = ref(false);
const selectedPurchase = ref(null);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchPurchases = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/purchases', {
            params: {
                search: searchQuery.value,
                status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
                from: dateFrom.value || undefined,
                to: dateTo.value || undefined,
                page: page,
                per_page: 15,
            },
        });
        purchases.value = response.data?.data || [];
        metrics.value = response.data?.summary || {
            total_purchases: 0,
            unpaid_total: 0,
            confirmed_count: 0,
        };
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 15,
            total: purchases.value.length,
        };
    } catch (error) {
        console.error('Failed to load purchases:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchPurchases(1);
    }, 300);
};

const openDetailsModal = (p) => {
    selectedPurchase.value = p;
    showDetailsModal.value = true;
};

const cancelPurchase = async (p) => {
    const result = await Swal.fire({
        title: trans('purchases.cancel_po_confirm_title', { number: p.purchase_number }),
        text: trans('purchases.cancel_po_confirm_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('common.yes'),
        cancelButtonText: trans('common.cancel'),
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.post(`/purchases/${p.id}/cancel`, { reason: trans('purchases.cancel_reason_default') });
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('purchases.purchase_cancelled_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchPurchases(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: error.userMessage || trans('purchases.purchase_cancelled_failed'),
            });
        }
    }
};

onMounted(() => {
    fetchPurchases(1);
});
</script>
