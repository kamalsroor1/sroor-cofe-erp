<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Action Controls -->
    <PageHeader :title="$t('trash.trash_title')" :subtitle="$t('trash.trash_subtitle')" icon="🗑️">
      <template #actions>
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          :loading="isLoading"
          @click="fetchRecords"
          class="flex items-center gap-2"
        >
          <RefreshCw class="w-4 h-4 text-theme-primary" :class="{ 'animate-spin': isLoading }" />
          <span>{{ $t('trash.refresh_trash') }}</span>
        </BaseButton>
      </template>
    </PageHeader>

    <!-- Module Tabs with Live Badges -->
    <TrashModuleTabs :current-tab="currentTab" :tabs-list="tabsList" :counts="counts" @change-tab="changeTab" />

    <!-- Search Input -->
    <TrashFilterBar :search="search" @update:search="updateSearch" />

    <!-- Records Table & Mobile Cards -->

    <template v-if="error && (!items || items.length === 0)">
      <ErrorState
        data-testid="error-state"
        :message="errorMessage"
        @retry="fetchTrash(pagination?.current_page || 1)"
      />
    </template>
    <template v-else>
      <div
        v-if="error"
        class="bg-rose-50 text-rose-500 p-3 rounded-lg mb-4 flex justify-between items-center"
        data-testid="error-state"
      >
        <span>{{ errorMessage }}</span>
        <button
          @click="fetchTrash(pagination?.current_page || 1)"
          data-testid="retry-button"
          class="underline font-bold"
        >
          {{ $t('connectivity.retry') }}
        </button>
      </div>
      <TrashTable
        :records="records"
        :pagination="pagination"
        :loading="isLoading"
        @restore="restoreRecord"
        @force-delete="forceDeleteRecord"
        @page-change="changePage"
      />
    </template>
  </div>
</template>

<script setup>
import ErrorState from '../../Components/Common/ErrorState.vue';
import { RefreshCw } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import TrashModuleTabs from '../../Components/Trash/TrashModuleTabs.vue';
import TrashFilterBar from '../../Components/Trash/TrashFilterBar.vue';
import TrashTable from '../../Components/Trash/TrashTable.vue';
import { useTrash } from '../../Composables/useTrash';

const {
  currentTab,
  search,
  records,
  counts,
  error,
  errorMessage,
  isLoading,
  tabsList,
  pagination,
  updateSearch,
  changeTab,
  fetchRecords,
  changePage,
  restoreRecord,
  forceDeleteRecord,
} = useTrash();
</script>
