<template>
  <div class="space-y-4 sm:space-y-5 max-w-7xl mx-auto font-tajawal transition-colors duration-300">
    <DashboardWelcomeBanner
      :company-name="appConfigStore.companyName || appConfigStore.platformName"
      :loading="isLoading"
      :can-sell="canSell"
      @refresh="retry"
    />

    <DashboardSkeleton v-if="isLoading && !dashboardData" />

    <ErrorState v-else-if="error && !dashboardData" :message="errorMessage" :loading="isLoading" @retry="retry" />

    <template v-else>
      <InlineErrorBar v-if="error" :message="errorMessage" @retry="retry" />

      <DashboardOwnShift
        v-if="isOwnShift"
        :metrics="metrics"
        :active-shift="activeShift"
        :recent-invoices="recentInvoices"
        :low-stock-items="lowStockItems"
        :quick-actions="quickActions"
        :can-sell="canSell"
        @preview="previewInvoice"
      />

      <template v-else>
        <DashboardKpiGrid :metrics="metrics" />
        <DashboardQuickActions :actions="quickActions" />

        <DashboardSetupChecklist
          v-if="isChecklistVisible"
          :steps="checklistSteps"
          :progress="setupProgress"
          @dismiss="dismissChecklist"
        />

        <DashboardFirstSale v-if="isNewShop" :can-sell="canSell" />
        <template v-else>
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
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAppConfigStore } from '../stores/appConfig';
import { useDashboard } from '../Composables/useDashboard';
import { useDashboardQuickActions } from '../Composables/useDashboardQuickActions';
import { useSetupChecklist } from '../Composables/useSetupChecklist';
import { isEmptyShop } from '../helpers/dashboardMetrics';

import ErrorState from '../Components/Common/ErrorState.vue';
import InlineErrorBar from '../Components/Common/InlineErrorBar.vue';
import DashboardWelcomeBanner from '../Components/Dashboard/DashboardWelcomeBanner.vue';
import DashboardOwnShift from '../Components/Dashboard/DashboardOwnShift.vue';
import DashboardKpiGrid from '../Components/Dashboard/DashboardKpiGrid.vue';
import DashboardQuickActions from '../Components/Dashboard/DashboardQuickActions.vue';
import DashboardSetupChecklist from '../Components/Dashboard/DashboardSetupChecklist.vue';
import DashboardFirstSale from '../Components/Dashboard/DashboardFirstSale.vue';
import DashboardAnalyticsRow from '../Components/Dashboard/DashboardAnalyticsRow.vue';
import DashboardPeakHours from '../Components/Dashboard/DashboardPeakHours.vue';
import DashboardRecentInvoices from '../Components/Dashboard/DashboardRecentInvoices.vue';
import DashboardLowStock from '../Components/Dashboard/DashboardLowStock.vue';
import DashboardSkeleton from '../Components/Dashboard/DashboardSkeleton.vue';

const router = useRouter();
const appConfigStore = useAppConfigStore();

const {
  dashboardData,
  isOwnShift,
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

const { actions: quickActions, canSell } = useDashboardQuickActions();

const {
  steps: checklistSteps,
  progress: setupProgress,
  isVisible: isChecklistVisible,
  dismiss: dismissChecklist,
} = useSetupChecklist();

const isNewShop = computed(() =>
  isEmptyShop({ metrics: metrics.value, recentInvoices: recentInvoices.value, period: periodAnalytics.value })
);

const previewInvoice = (inv) => router.push('/invoices?view=' + inv.id);
</script>
