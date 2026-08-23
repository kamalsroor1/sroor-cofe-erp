<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Action Controls -->
    <PageHeader
      :title="$t('activity.title')"
      :subtitle="$t('activity.subtitle')"
      icon="📜"
    >
      <template #actions>
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          :loading="isLoading"
          @click="fetchLogs"
          class="flex items-center gap-2"
        >
          <RefreshCw class="w-4 h-4 text-theme-primary" :class="{ 'animate-spin': isLoading }" />
          <span>{{ $t('activity.refresh_log') }}</span>
        </BaseButton>
      </template>
    </PageHeader>

    <!-- Activity Stats KPI Grid -->
    <ActivityLogsMetricsGrid
      :stats="stats"
      :loading="isLoading && !logs.length"
    />

    <!-- Filter Controls Bar -->
    <ActivityLogsFilterBar
      :search="filters.search"
      :module="filters.module"
      :user-id="filters.user_id"
      :store-id="filters.store_id"
      :module-options="moduleOptions"
      :user-options="userOptions"
      :store-options="storeOptions"
      @update:search="updateSearch"
      @update:module="updateModule"
      @update:user-id="updateUserId"
      @update:store-id="updateStoreId"
    />

    <!-- Logs Timeline & Pagination -->
    <ActivityLogsTimeline
      :logs="logs"
      :pagination="pagination"
      :loading="isLoading"
      @inspect="openDetails"
      @page-change="changePage"
    />

    <!-- Payload Details Modal -->
    <ActivityLogDetailsModal
      :selected-log="selectedLog"
      @close="closeDetails"
    />
  </div>
</template>

<script setup>
import { RefreshCw } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import ActivityLogsMetricsGrid from '../../Components/ActivityLogs/ActivityLogsMetricsGrid.vue';
import ActivityLogsFilterBar from '../../Components/ActivityLogs/ActivityLogsFilterBar.vue';
import ActivityLogsTimeline from '../../Components/ActivityLogs/ActivityLogsTimeline.vue';
import ActivityLogDetailsModal from '../../Components/ActivityLogs/ActivityLogDetailsModal.vue';
import { useActivityLogs } from '../../Composables/useActivityLogs';

const {
  logs,
  stats,
  filters,
  pagination,
  moduleOptions,
  userOptions,
  storeOptions,
  isLoading,
  selectedLog,
  updateSearch,
  updateModule,
  updateUserId,
  updateStoreId,
  fetchLogs,
  changePage,
  openDetails,
  closeDetails,
} = useActivityLogs();
</script>
