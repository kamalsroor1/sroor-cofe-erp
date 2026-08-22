<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <PageHeader
        :title="$t('inventory.branch_stocks_balance')"
        :subtitle="$t('inventory.branch_stocks_subtitle')"
        :icon="'📦'"
      >
        <template #actions>
          <router-link
            to="/stores"
            class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-2 font-tajawal shadow-sm"
          >
            <ArrowRight class="w-4 h-4" />
            <span>{{ $t('inventory.back_to_stores') }}</span>
          </router-link>
        </template>
      </PageHeader>

      <!-- Filters Bar -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Store Selector Dropdown -->
        <div class="flex items-center gap-2">
          <label class="text-xs font-bold text-slate-400 whitespace-nowrap font-tajawal">
            🏬 {{ $t('inventory.store') }}:
          </label>
          <select
            v-model="selectedStoreId"
            @change="fetchStocks(1)"
            class="h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-200 focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal cursor-pointer min-w-[180px]"
          >
            <option v-for="store in stores" :key="store.id" :value="store.id">
              {{ store.name }} {{ store.is_main ? `(${ $t('inventory.main_store') })` : '' }}
            </option>
          </select>
        </div>

        <!-- Search & Status Filters -->
        <div class="flex flex-col sm:flex-row items-center gap-2 flex-1 md:justify-end">
          <!-- Search Input -->
          <div class="relative w-full sm:w-64">
            <input
              v-model="searchQuery"
              @input="debounceSearch"
              type="text"
              class="w-full h-10 pr-9 pl-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
              :placeholder="$t('inventory.search_item_code')"
            >
            <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
          </div>

          <!-- Stock Status Filter -->
          <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800 w-full sm:w-auto">
            <button
              type="button"
              @click="setStockStatus('all')"
              class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all cursor-pointer"
              :class="stockStatus === 'all' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            >
              {{ $t('common.all') }}
            </button>

            <button
              type="button"
              @click="setStockStatus('low')"
              class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all cursor-pointer"
              :class="stockStatus === 'low' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            >
              ⚠️ {{ $t('inventory.low_stock') }}
            </button>

            <button
              type="button"
              @click="setStockStatus('out')"
              class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all cursor-pointer"
              :class="stockStatus === 'out' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            >
              🚨 {{ $t('inventory.out_of_stock') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Stocks Table -->
      <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <!-- Loading State -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="stocks.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 font-tajawal border-b border-slate-200 dark:border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.item_name') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.item_code') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.unit') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.current_stock') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.min_stock_level') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.cost_price') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.total_valuation') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr
                v-for="(stock, idx) in stocks"
                :key="stock.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-100 dark:hover:bg-slate-900/50 transition-colors"
                :class="stock.is_out_of_stock ? 'bg-rose-500/5' : (stock.is_low_stock ? 'bg-amber-500/5' : '')"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}</td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">{{ stock.item_name }}</td>
                <td class="py-3.5 px-4 font-mono text-slate-400">{{ stock.item_code }}</td>
                <td class="py-3.5 px-4 text-center font-tajawal text-slate-300">{{ stock.unit || $t('inventory.unit_piece_short') }}</td>
                <td class="py-3.5 px-4 text-center font-mono font-black text-sm" :class="stock.is_out_of_stock ? 'text-rose-400' : (stock.is_low_stock ? 'text-amber-400' : 'text-emerald-400')">
                  {{ formatDecimal(stock.quantity) }}
                </td>
                <td class="py-3.5 px-4 text-center font-mono text-slate-400">{{ formatDecimal(stock.min_stock_level) }}</td>
                <td class="py-3.5 px-4 text-end font-mono text-slate-300">{{ formatMoney(stock.cost_price) }} {{ $t('common.currency') }}</td>
                <td class="py-3.5 px-4 text-end font-mono font-bold text-amber-400">{{ formatMoney(stock.total_valuation) }} {{ $t('common.currency') }}</td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    v-if="stock.is_out_of_stock"
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30 font-tajawal"
                  >
                    {{ $t('inventory.out_of_stock_badge') }}
                  </span>
                  <span
                    v-else-if="stock.is_low_stock"
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 font-tajawal"
                  >
                    {{ $t('inventory.low_stock_badge') }}
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-tajawal"
                  >
                    {{ $t('inventory.available_badge') }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :title="$t('inventory.no_stocks_found')"
          :description="$t('inventory.no_stocks_match_filter')"
          :icon="'📦'"
        />

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400 font-tajawal">
            {{ $t('inventory.total_results_items', { count: pagination.total }) }}
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchStocks(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              {{ $t('common.previous') }}
            </button>
            <span class="px-3 py-1.5 text-xs font-mono text-slate-300 font-bold">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchStocks(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              {{ $t('common.next') }}
            </button>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import api from '../../services/api';
import {
    ArrowRight,
    Search
} from 'lucide-vue-next';

const route = useRoute();
const stores = ref([]);
const stocks = ref([]);
const selectedStoreId = ref(parseInt(route.query.store_id || '1', 10));
const searchQuery = ref('');
const stockStatus = ref('all');
const isLoading = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

let debounceTimeout = null;

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDecimal = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
};

const fetchStores = async () => {
    try {
        const response = await api.get('/stores');
        stores.value = response.data?.stores || [];
        if (!selectedStoreId.value && stores.value.length > 0) {
            selectedStoreId.value = stores.value[0].id;
        }
    } catch (e) {
        console.error('Failed to load stores:', e);
    }
};

const fetchStocks = async (page = 1) => {
    if (!selectedStoreId.value) return;

    isLoading.value = true;
    try {
        const response = await api.get('/stores/stocks', {
            params: {
                store_id: selectedStoreId.value,
                search: searchQuery.value,
                stock_status: stockStatus.value,
                page: page,
                per_page: 20,
            },
        });
        stocks.value = response.data?.data || [];
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 20,
            total: stocks.value.length,
        };
    } catch (error) {
        console.error('Failed to load store stocks:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchStocks(1);
    }, 300);
};

const setStockStatus = (status) => {
    stockStatus.value = status;
    fetchStocks(1);
};

onMounted(async () => {
    await fetchStores();
    await fetchStocks(1);
});
</script>
