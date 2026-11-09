<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <DashboardWelcomeBanner
      :company-name="appConfigStore.companyName || appConfigStore.platformName"
      :loading="isLoading"
      @refresh="retry"
    />

    <DashboardSetupChecklist
      v-if="isChecklistVisible"
      :steps="checklistSteps"
      :progress="setupProgress"
      @dismiss="dismissChecklist"
    />

    <!-- 🚀 Metro App Menu Hub (Tiles & Operations Launcher) -->
    <DashboardAppMenuHub />

    <!-- 🔄 Facebook-Style Skeleton Loading State -->
    <DashboardSkeleton v-if="isLoading && !dashboardData" />

    <ErrorState v-else-if="error && !dashboardData" :message="errorMessage" :loading="isLoading" @retry="retry" />

    <template v-else>
      <InlineErrorBar v-if="error" :message="errorMessage" @retry="retry" />
      <DashboardKpiGrid :metrics="metrics" />

      <DashboardAnalyticsRow
        :daily-trend="dailyTrend"
        :period="periodAnalytics"
        :payment-distribution="paymentDistribution"
        :active-shift="activeShift"
      />

      <DashboardPeakHours :hourly-sales="hourlySales" :peak-hour="peakHour" />

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8">
          <DashboardRecentInvoices :invoices="recentInvoices" @preview="previewInvoice" />
        </div>
        <div class="lg:col-span-4">
          <DashboardLowStock :items="lowStockItems" />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAppConfigStore } from '../stores/appConfig';
import { useDashboard } from '../Composables/useDashboard';
import { useSetupChecklist } from '../Composables/useSetupChecklist';

import ErrorState from '../Components/Common/ErrorState.vue';
import InlineErrorBar from '../Components/Common/InlineErrorBar.vue';
import DashboardWelcomeBanner from '../Components/Dashboard/DashboardWelcomeBanner.vue';
import DashboardSetupChecklist from '../Components/Dashboard/DashboardSetupChecklist.vue';
import DashboardAppMenuHub from '../Components/Dashboard/DashboardAppMenuHub.vue';
import DashboardKpiGrid from '../Components/Dashboard/DashboardKpiGrid.vue';
import DashboardAnalyticsRow from '../Components/Dashboard/DashboardAnalyticsRow.vue';
import DashboardPeakHours from '../Components/Dashboard/DashboardPeakHours.vue';
import DashboardRecentInvoices from '../Components/Dashboard/DashboardRecentInvoices.vue';
import DashboardLowStock from '../Components/Dashboard/DashboardLowStock.vue';
import DashboardSkeleton from '../Components/Dashboard/DashboardSkeleton.vue';

const router = useRouter();
const appConfigStore = useAppConfigStore();

const {
  dashboardData,
  isLoading,
  error,
  errorMessage,
  retry,
  metrics,
  dailyTrend,
  hourlySales,
  peakHour,
  paymentDistribution,
  periodAnalytics,
  recentInvoices,
  lowStockItems,
  activeShift,
} = useDashboard();

const {
  steps: checklistSteps,
  progress: setupProgress,
  isVisible: isChecklistVisible,
  dismiss: dismissChecklist,
} = useSetupChecklist();

const previewInvoice = (inv) => router.push('/invoices?view=' + inv.id);
</script>
