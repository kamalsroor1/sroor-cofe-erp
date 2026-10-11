<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader :title="$t('returns.title')" :subtitle="$t('returns.subtitle')" icon="🔄">
      <template #actions>
        <router-link
          to="/returns/create"
          class="px-5 py-2.5 bg-theme-gradient text-white font-black shadow-theme-primary rounded-xl text-xs transition-all flex items-center gap-2 shadow-lg active:scale-95"
        >
          <Plus class="w-4 h-4" />
          <span>{{ $t('returns.new_return') }}</span>
        </router-link>
      </template>
    </PageHeader>

    <!-- Financial Metrics Grid -->
    <ReturnsMetricsGrid :summary="summary" :loading="isLoading" />

    <!-- Filters & Search Bar -->
    <ReturnsFilterBar
      :search-query="searchQuery"
      :selected-type="selectedType"
      :type-options="typeOptions"
      :date-from="dateFrom"
      :date-to="dateTo"
      @update:search-query="updateSearch"
      @update:selected-type="updateType"
      @update:date-from="updateDateFrom"
      @update:date-to="updateDateTo"
    />

    <!-- Returns Ledger Table & Mobile Cards -->

    <template v-if="error && (!returnsList || returnsList.length === 0)">
      <ErrorState
        data-testid="error-state"
        :message="errorMessage"
        @retry="fetchReturns(pagination?.current_page || 1)"
      />
    </template>
    <template v-else>
      <InlineErrorBar v-if="error" :message="errorMessage" @retry="fetchReturns(pagination?.current_page || 1)" />
      <ReturnsTable
        :returns-list="returnsList"
        :pagination="pagination"
        :loading="isLoading"
        @open-details="openDetailsModal"
        @delete-return="deleteReturnDoc"
        @page-change="fetchReturns"
      />
    </template>

    <!-- Return Details Modal -->
    <ReturnDetailsModal
      :show="showDetailsModal"
      :return-details="selectedReturnDetails"
      @close="showDetailsModal = false"
    />
  </div>
</template>

<script setup>
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import { Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import ReturnsMetricsGrid from '../../Components/Returns/ReturnsMetricsGrid.vue';
import ReturnsFilterBar from '../../Components/Returns/ReturnsFilterBar.vue';
import ReturnsTable from '../../Components/Returns/ReturnsTable.vue';
import ReturnDetailsModal from '../../Components/Returns/ReturnDetailsModal.vue';
import { useReturns } from '../../Composables/useReturns';

const {
  returnsList,
  summary,
  searchQuery,
  selectedType,
  typeOptions,
  dateFrom,
  dateTo,
  error,
  errorMessage,
  isLoading,
  pagination,
  showDetailsModal,
  selectedReturnDetails,
  fetchReturns,
  updateSearch,
  updateType,
  updateDateFrom,
  updateDateTo,
  openDetailsModal,
  deleteReturnDoc,
} = useReturns();
</script>
