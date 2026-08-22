<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('inventory.transfers_title')"
        :subtitle="$t('inventory.transfers_subtitle')"
        :icon="'🚚'"
      >
        <template #actions>
          <router-link
            to="/stock-transfers/create"
            class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-amber-500/20"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('inventory.new_transfer_order') }}</span>
          </router-link>
        </template>
      </PageHeader>

      <!-- Summary Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Transfers Count -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('inventory.total_transfers_count') }}</span>
            <Truck class="w-4 h-4 text-amber-400" />
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
            {{ summary.total_count || 0 }} <span class="text-xs text-slate-400">{{ $t('inventory.transfer_doc_unit') }}</span>
          </div>
          <span class="text-[10px] text-slate-500">{{ $t('inventory.total_transfers_count_sub') }}</span>
        </div>

        <!-- Confirmed Transfers -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('inventory.confirmed_transfers_title') }}</span>
            <CheckCircle2 class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="text-2xl font-black text-emerald-400 font-mono">
            {{ summary.confirmed_count || 0 }} <span class="text-xs text-slate-400">{{ $t('inventory.transfer_status_done') }}</span>
          </div>
          <span class="text-[10px] text-slate-500">{{ $t('inventory.confirmed_transfers_sub') }}</span>
        </div>

        <!-- Cancelled Transfers -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('inventory.cancelled_transfers_title') }}</span>
            <Ban class="w-4 h-4 text-rose-400" />
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ summary.cancelled_count || 0 }} <span class="text-xs text-slate-400">{{ $t('inventory.transfer_status_cancelled') }}</span>
          </div>
          <span class="text-[10px] text-slate-500">{{ $t('inventory.cancelled_transfers_sub') }}</span>
        </div>
      </div>

      <!-- Filters & Search Bar -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none"
            :placeholder="$t('inventory.search_transfers_placeholder')"
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- From Store Filter -->
        <div class="w-full md:w-40">
          <select
            v-model="fromStoreId"
            @change="fetchTransfers(1)"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option value="all">{{ $t('inventory.all_from_stores') }}</option>
            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>

        <!-- To Store Filter -->
        <div class="w-full md:w-40">
          <select
            v-model="toStoreId"
            @change="fetchTransfers(1)"
            class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option value="all">{{ $t('inventory.all_to_stores') }}</option>
            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="flex items-center gap-2">
          <input
            v-model="dateFrom"
            @change="fetchTransfers(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
          <span class="text-xs text-slate-500 font-bold">—</span>
          <input
            v-model="dateTo"
            @change="fetchTransfers(1)"
            type="date"
            class="h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
        </div>
      </div>

      <!-- Transfers Ledger Table -->
      <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="transfersList.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.transfer_number') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.from_store') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.to_store') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.transfer_items') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
              <tr
                v-for="(trf, idx) in transfersList"
                :key="trf.id"
                class="hover:bg-slate-900/50 transition-colors"
                :class="trf.is_cancelled ? 'opacity-50 line-through bg-rose-500/5' : ''"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                  {{ trf.transfer_number }}
                </td>
                <td class="py-3.5 px-4 font-bold text-slate-300 font-tajawal">
                  {{ trf.from_store_name }}
                </td>
                <td class="py-3.5 px-4 font-bold text-emerald-400 font-tajawal">
                  {{ trf.to_store_name }}
                </td>
                <td class="py-3.5 px-4 font-mono text-slate-300">
                  {{ trf.transfer_date }}
                </td>
                <td class="py-3.5 px-4 text-center font-mono font-bold text-cyan-400">
                  {{ trf.items_count }} {{ $t('inventory.item_unit') }}
                </td>
                <td class="py-3.5 px-4 text-center font-tajawal">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="!trf.is_cancelled ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
                  >
                    {{ !trf.is_cancelled ? $t('inventory.transfer_status_done') : $t('inventory.transfer_status_cancelled') }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Preview Details Button -->
                    <button
                      type="button"
                      @click="openDetailsModal(trf)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="$t('inventory.view_transfer_details_hint')"
                    >
                      <Eye class="w-4 h-4" />
                    </button>

                    <!-- Cancel Transfer Button -->
                    <button
                      v-if="!trf.is_cancelled"
                      type="button"
                      @click="cancelTransferDoc(trf)"
                      class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
                      :title="$t('inventory.cancel_transfer_hint')"
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
          :title="$t('inventory.no_transfers_found')"
          :description="$t('inventory.no_transfers_found')"
          :icon="'🚚'"
        >
          <template #action>
            <router-link
              to="/stock-transfers/create"
              class="px-5 py-2.5 bg-amber-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20"
            >
              {{ $t('inventory.create_first_transfer') }}
            </router-link>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400">
            {{ $t('inventory.total_results_transfers', { count: pagination.total }) }}
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchTransfers(pagination.current_page - 1)"
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
              @click="fetchTransfers(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Transfer Details Modal -->
      <AppModal
        :show="showDetailsModal"
        :title="$t('inventory.transfer_details_modal_title', { number: selectedTransferDetails?.transfer_number || '' })"
        @close="showDetailsModal = false"
      >
        <div v-if="selectedTransferDetails" class="space-y-4 font-tajawal text-xs">
          <!-- Top Info Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl">
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('inventory.from_store') }}:</span>
              <span class="font-bold text-amber-400">{{ selectedTransferDetails.from_store_name }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('inventory.to_store') }}:</span>
              <span class="font-bold text-emerald-400">{{ selectedTransferDetails.to_store_name }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('common.date') }}:</span>
              <span class="text-slate-200 font-mono">{{ selectedTransferDetails.transfer_date }}</span>
            </div>
            <div>
              <span class="text-slate-400 block font-bold">{{ $t('inventory.store_user') }}:</span>
              <span class="text-slate-200">{{ selectedTransferDetails.user_name }}</span>
            </div>
          </div>

          <!-- Items Table -->
          <div class="border border-slate-800 rounded-xl overflow-hidden">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-slate-900 text-slate-400 border-b border-slate-800">
                  <th class="p-2.5 text-start font-bold">{{ $t('inventory.item_name') }}</th>
                  <th class="p-2.5 text-start font-bold">{{ $t('inventory.code') }}</th>
                  <th class="p-2.5 text-end font-bold">{{ $t('inventory.transferred_qty_col') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/50">
                <tr v-for="it in selectedTransferDetails.items" :key="it.id">
                  <td class="p-2.5 font-bold text-white">{{ it.item_name }}</td>
                  <td class="p-2.5 font-mono text-slate-400">{{ it.item_code || '—' }}</td>
                  <td class="p-2.5 text-end font-mono font-bold text-cyan-400 text-sm">
                    {{ it.quantity }} {{ it.unit }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="selectedTransferDetails.notes" class="p-3 bg-slate-900/50 border border-slate-800/80 rounded-xl text-slate-400">
            <span class="font-bold text-slate-300">{{ $t('inventory.transfer_notes_label') }}</span>
            <span>{{ selectedTransferDetails.notes }}</span>
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
    Truck,
    CheckCircle2,
    Ban,
    Eye
} from 'lucide-vue-next';

const transfersList = ref([]);
const stores = ref([]);
const summary = ref({
    total_count: 0,
    confirmed_count: 0,
    cancelled_count: 0,
});

const searchQuery = ref('');
const fromStoreId = ref('all');
const toStoreId = ref('all');
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
const selectedTransferDetails = ref(null);

const loadStores = async () => {
    try {
        const response = await api.get('/stores');
        stores.value = response.data?.data || [];
    } catch (error) {
        console.error('Failed to load stores:', error);
    }
};

const fetchTransfers = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/transfers', {
            params: {
                search: searchQuery.value || undefined,
                from_store_id: fromStoreId.value !== 'all' ? fromStoreId.value : undefined,
                to_store_id: toStoreId.value !== 'all' ? toStoreId.value : undefined,
                from_date: dateFrom.value || undefined,
                to_date: dateTo.value || undefined,
                page: page,
                per_page: 15,
            },
        });
        transfersList.value = response.data?.data || [];
        summary.value = response.data?.summary || {
            total_count: 0,
            confirmed_count: 0,
            cancelled_count: 0,
        };
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 15,
            total: transfersList.value.length,
        };
    } catch (error) {
        console.error('Failed to load transfers:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchTransfers(1);
    }, 300);
};

const openDetailsModal = async (trf) => {
    try {
        const response = await api.get(`/transfers/${trf.id}`);
        selectedTransferDetails.value = response.data?.data;
        showDetailsModal.value = true;
    } catch (error) {
        console.error('Failed to load transfer details:', error);
    }
};

const cancelTransferDoc = async (trf) => {
    const { value: reason } = await Swal.fire({
        title: trans('inventory.cancel_transfer_confirm_title', { number: trf.transfer_number }),
        text: trans('inventory.cancel_transfer_confirm_text'),
        input: 'text',
        inputPlaceholder: trans('inventory.cancel_reason_placeholder'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: trans('inventory.cancel_transfer_btn'),
        cancelButtonText: trans('common.cancel'),
        confirmButtonColor: '#f43f5e',
    });

    if (reason !== undefined) {
        try {
            await api.post(`/transfers/${trf.id}/cancel`, { reason: reason || 'إلغاء من النظام' });
            Swal.fire({
                icon: 'success',
                title: trans('common.success'),
                text: trans('inventory.transfer_cancelled_success'),
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchTransfers(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: trans('common.error'),
                text: error.userMessage || trans('inventory.transfer_cancel_failed'),
            });
        }
    }
};

onMounted(() => {
    loadStores();
    fetchTransfers(1);
});
</script>
