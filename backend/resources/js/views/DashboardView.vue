<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal transition-colors duration-300">

    <DashboardWelcomeBanner
      :company-name="appConfigStore.companyName"
      :loading="isLoading"
      @refresh="fetchDashboard"
    />

    <!-- 🔄 Facebook-Style Skeleton Loading State -->
    <DashboardSkeleton v-if="isLoading && !dashboardData" />

    <template v-else>
      <DashboardKpiGrid :metrics="metrics" />

      <DashboardAnalyticsRow
        :daily-trend="dailyTrend"
        :period="periodAnalytics"
        :payment-distribution="paymentDistribution"
        :active-shift="activeShift"
      />

      <DashboardPeakHours
        :hourly-sales="hourlySales"
        :peak-hour="peakHour"
      />

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
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAppConfigStore } from '../stores/appConfig';
import api from '../services/api';

import DashboardWelcomeBanner  from '../Components/Dashboard/DashboardWelcomeBanner.vue';
import DashboardKpiGrid        from '../Components/Dashboard/DashboardKpiGrid.vue';
import DashboardAnalyticsRow   from '../Components/Dashboard/DashboardAnalyticsRow.vue';
import DashboardPeakHours      from '../Components/Dashboard/DashboardPeakHours.vue';
import DashboardRecentInvoices from '../Components/Dashboard/DashboardRecentInvoices.vue';
import DashboardLowStock       from '../Components/Dashboard/DashboardLowStock.vue';
import DashboardSkeleton       from '../Components/Dashboard/DashboardSkeleton.vue';

const router = useRouter();
const appConfigStore = useAppConfigStore();

const dashboardData = ref(null);
const isLoading     = ref(true);

const metrics             = computed(() => dashboardData.value?.metrics || {});
const analytics           = computed(() => dashboardData.value?.analytics || {});
const dailyTrend          = computed(() => analytics.value?.daily_trend || []);
const hourlySales         = computed(() => analytics.value?.hourly_sales || []);
const peakHour            = computed(() => analytics.value?.peak_hour || null);
const paymentDistribution = computed(() => analytics.value?.payment_distribution || []);
const periodAnalytics     = computed(() => analytics.value?.period || {});
const recentInvoices      = computed(() => dashboardData.value?.recent_invoices || []);
const lowStockItems       = computed(() => dashboardData.value?.low_stock_items || []);
const activeShift         = computed(() => dashboardData.value?.active_shift || null);

const previewInvoice = (inv) => router.push('/invoices?view=' + inv.id);

const fetchDashboard = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/dashboard');
        dashboardData.value = res.data?.data;
    } catch (e) {
        console.error('Failed to load dashboard data:', e);
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchDashboard);
</script>