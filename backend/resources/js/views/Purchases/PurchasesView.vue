<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('purchases.purchases_list')"
      :subtitle="$t('purchases.purchases_list_sub')"
      icon="🚛"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <!-- Smart Reorder Link -->
          <router-link
            to="/purchases/smart-reorder"
            class="px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-lg shadow-purple-500/20"
          >
            <Sparkles class="w-4 h-4 text-amber-300" />
            <span>{{ $t('purchases.smart_reorder_radar') }}</span>
          </router-link>

          <!-- Create Purchase Button -->
          <router-link
            to="/purchases/create"
            class="px-4 py-2 bg-theme-gradient text-white shadow-theme-primary font-black rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-lg shadow-theme-primary"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('purchases.new_purchase') }}</span>
          </router-link>
        </div>
      </template>
    </PageHeader>

    <!-- Summary Metrics Grid -->
    <PurchasesMetricsGrid
      :metrics="metrics"
      :loading="isLoading"
    />

    <!-- Filters & Search Bar -->
    <PurchasesFilterBar
      v-model:search-query="searchQuery"
      v-model:selected-status="selectedStatus"
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      :status-options="statusOptions"
      @search="debounceSearch"
      @filter="fetchPurchases(1)"
    />

    <!-- Purchases Table & Mobile Cards -->
    <PurchasesTable
      :purchases="purchases"
      :pagination="pagination"
      :loading="isLoading"
      @preview="openDetailsModal"
      @cancel="cancelPurchase"
      @page-change="fetchPurchases"
    />

    <!-- Purchase Details Modal -->
    <PurchaseDetailsModal
      :show="showDetailsModal"
      :purchase="selectedPurchase"
      @close="showDetailsModal = false"
    />
  </div>
</template>

<script setup>
import { Plus, Sparkles } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import PurchasesMetricsGrid from '../../Components/Purchases/PurchasesMetricsGrid.vue';
import PurchasesFilterBar from '../../Components/Purchases/PurchasesFilterBar.vue';
import PurchasesTable from '../../Components/Purchases/PurchasesTable.vue';
import PurchaseDetailsModal from '../../Components/Purchases/PurchaseDetailsModal.vue';
import { usePurchases } from '../../Composables/usePurchases';

const {
  purchases,
  metrics,
  searchQuery,
  selectedStatus,
  statusOptions,
  dateFrom,
  dateTo,
  isLoading,
  pagination,
  showDetailsModal,
  selectedPurchase,
  fetchPurchases,
  debounceSearch,
  openDetailsModal,
  cancelPurchase,
} = usePurchases();
</script>
