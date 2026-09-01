<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('inventory.branch_stocks_balance')"
      :subtitle="$t('inventory.branch_stocks_subtitle')"
      icon="📦"
    >
      <template #actions>
        <router-link
          to="/stores"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
        >
          <ArrowRight class="w-4 h-4" />
          <span>{{ $t('inventory.back_to_stores') }}</span>
        </router-link>
      </template>
    </PageHeader>

    <!-- Filters Bar -->
    <StoreStocksFilterBar
      v-model:selected-store-id="selectedStoreId"
      v-model:search-query="searchQuery"
      :store-options="storeOptions"
      :stock-status="stockStatus"
      @store-change="fetchStocks(1)"
      @search="debounceSearch"
      @set-status="setStockStatus"
    />

    <!-- Stocks Table & Mobile Cards -->
    <StoreStocksTable
      :stocks="stocks"
      :pagination="pagination"
      :loading="isLoading"
      @page-change="fetchStocks"
    />
  </div>
</template>

<script setup>
import { ArrowRight } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import StoreStocksFilterBar from '../../Components/StoreStocks/StoreStocksFilterBar.vue';
import StoreStocksTable from '../../Components/StoreStocks/StoreStocksTable.vue';
import { useStoreStocks } from '../../Composables/useStoreStocks';

const {
  selectedStoreId,
  searchQuery,
  stockStatus,
  isLoading,
  stocks,
  pagination,
  storeOptions,
  fetchStocks,
  debounceSearch,
  setStockStatus,
} = useStoreStocks();
</script>
