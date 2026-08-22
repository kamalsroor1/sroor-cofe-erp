<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Welcome Header Banner -->
      <div class="p-6 rounded-3xl bg-white dark:bg-gradient-to-r dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-theme-light border border-theme-border rounded-full text-xs font-bold text-theme-primary mb-2">
            <span>{{ $t('dashboard.app_badge_sub') }}</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
            {{ $t('dashboard.welcome_user', { name: authStore.userName }) }}
          </h1>
          <p class="text-xs sm:text-sm text-slate-400 font-bold mt-1">
            {{ $t('dashboard.active_branch_label') }} <span class="text-theme-primary font-mono">{{ dashboardData?.active_store?.name || authStore.activeStoreName || $t('common.main_branch') }}</span>
          </p>
        </div>

        <div class="flex items-center gap-2.5">
          <router-link
            to="/pos"
            class="px-5 py-2.5 bg-theme-gradient text-white shadow-theme-primary font-black text-xs rounded-2xl shadow-lg shadow-theme-primary flex items-center gap-2 transition-all cursor-pointer"
          >
            <ShoppingCart class="w-4 h-4" />
            <span>{{ $t('dashboard.pos_fast_btn') }}</span>
          </router-link>

          <router-link
            to="/coffee-blender"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-theme-primary border border-slate-200 dark:border-slate-700 font-bold text-xs rounded-2xl shadow-md flex items-center gap-2 transition-all cursor-pointer"
          >
            <Layers class="w-4 h-4" />
            <span>{{ $t('dashboard.coffee_blend_btn') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading && !dashboardData" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400 font-bold">{{ $t('dashboard.loading_dashboard') }}</p>
      </div>

      <div v-else class="space-y-6">
        <!-- 4 Key Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Today Sales -->
          <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-400">{{ $t('dashboard.today_sales_card') }}</span>
              <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                <TrendingUp class="w-4 h-4" />
              </div>
            </div>
            <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
              {{ formatMoney(metrics.today_sales || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
            </div>
            <div class="text-[11px] text-slate-500">
              {{ $t('dashboard.today_invoices_count', { count: metrics.today_invoices_count || 0 }) }}
            </div>
          </div>

          <!-- Monthly Gross Profit & Margin -->
          <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-400">{{ $t('dashboard.monthly_gross_profit_card') }}</span>
              <div class="w-8 h-8 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center">
                <BarChart3 class="w-4 h-4" />
              </div>
            </div>
            <div class="text-2xl font-black text-theme-primary font-mono">
              {{ formatMoney(metrics.monthly_gross_profit || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
            </div>
            <div class="text-[11px] text-slate-500">
              {{ $t('dashboard.profit_margin_label') }} <span class="font-mono text-emerald-400 font-bold">{{ metrics.monthly_margin || '0.00' }}%</span>
            </div>
          </div>

          <!-- Customer Debts -->
          <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-400">{{ $t('dashboard.customers_debt_card') }}</span>
              <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                <Users class="w-4 h-4" />
              </div>
            </div>
            <div class="text-2xl font-black text-cyan-400 font-mono">
              {{ formatMoney(metrics.customers_debt || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
            </div>
            <div class="text-[11px] text-slate-500">
              {{ $t('dashboard.due_collections_label') }}
            </div>
          </div>

          <!-- Net Cash Collected Today -->
          <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-400">{{ $t('dashboard.net_cash') }}</span>
              <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
                <Wallet class="w-4 h-4" />
              </div>
            </div>
            <div class="text-2xl font-black font-mono" :class="metrics.net_cash_today >= 0 ? 'text-emerald-400' : 'text-rose-400'">
              {{ formatMoney(metrics.net_cash_today || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
            </div>
            <div class="text-[11px] text-slate-500">
              {{ $t('dashboard.net_cash_formula_sub') }}
            </div>
          </div>
        </div>

        <!-- 7-Day Trend Chart & Shift Status (2 Cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- 7-Day Sales Trend (Col span 2) -->
          <div class="lg:col-span-2 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5">
              <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <TrendingUp class="w-4 h-4 text-emerald-400" />
                <span>{{ $t('dashboard.seven_days_trend_title') }}</span>
              </h2>
              <span class="text-[11px] text-slate-500 font-mono">{{ $t('dashboard.daily_invoices_sub') }}</span>
            </div>

            <!-- Trend Bars -->
            <div v-if="trendDays.length > 0" class="space-y-3 pt-2">
              <div v-for="day in trendDays" :key="day.date" class="space-y-1">
                <div class="flex justify-between text-xs font-mono">
                  <span class="text-slate-400 font-sans">{{ day.day_name || day.date }}</span>
                  <span class="text-emerald-400 font-bold">{{ formatMoney(day.total_sales) }} {{ $t('common.currency') }}</span>
                </div>
                <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-900 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                    :style="{ width: `${getBarWidth(day.total_sales)}%` }"
                  ></div>
                </div>
              </div>
            </div>

            <div v-else class="p-8 text-center text-xs text-slate-500 font-bold">
              {{ $t('dashboard.no_sales_previous_days') }}
            </div>
          </div>

          <!-- Active Cash Shift Widget (Col span 1) -->
          <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5">
              <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <Wallet class="w-4 h-4 text-theme-primary" />
                <span>{{ $t('dashboard.active_shift_widget_title') }}</span>
              </h2>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                :class="dashboardData?.active_shift ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
              >
                {{ dashboardData?.active_shift ? $t('dashboard.shift_open_badge') : $t('dashboard.shift_closed_badge') }}
              </span>
            </div>

            <div v-if="dashboardData?.active_shift" class="space-y-3 font-mono text-xs">
              <div class="flex justify-between text-slate-400 font-sans">
                <span>{{ $t('treasury.shift_number', { number: '' }) }}</span>
                <span class="text-theme-primary font-bold font-mono">{{ dashboardData.active_shift.shift_number }}</span>
              </div>
              <div class="flex justify-between text-slate-400 font-sans">
                <span>{{ $t('dashboard.responsible_cashier') }}</span>
                <span class="text-white font-bold font-sans">{{ dashboardData.active_shift.user_name }}</span>
              </div>
              <div class="flex justify-between text-slate-400 font-sans">
                <span>{{ $t('dashboard.opening_balance_short') }}</span>
                <span class="text-slate-300">{{ formatMoney(dashboardData.active_shift.starting_cash) }} {{ $t('common.currency') }}</span>
              </div>
              <div class="flex justify-between text-slate-400 font-sans pt-2 border-t border-slate-200 dark:border-slate-800">
                <span>{{ $t('dashboard.expected_drawer_cash') }}</span>
                <span class="text-emerald-400 font-black text-base">{{ formatMoney(dashboardData.active_shift.current_cash) }} {{ $t('common.currency') }}</span>
              </div>
            </div>

            <div v-else class="p-6 text-center text-xs text-slate-500 font-bold space-y-2">
              <p>{{ $t('dashboard.no_shift_open_notice') }}</p>
              <router-link
                to="/daily-journal"
                class="inline-block px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 rounded-xl text-[11px] font-bold transition"
              >
                {{ $t('dashboard.open_new_shift_link') }}
              </router-link>
            </div>
          </div>
        </div>

        <!-- 3 Operational Grids: Low Stock, Recent Invoices, Top Selling -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Low Stock Radar (Col 1) -->
          <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-3">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
              <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <AlertOctagon class="w-4 h-4 text-rose-400" />
                <span>{{ $t('dashboard.low_stock_radar_count', { count: lowStockItems.length }) }}</span>
              </h2>
              <router-link to="/purchases/smart-reorder" class="text-[11px] text-amber-400 hover:underline">
                {{ $t('dashboard.smart_reorder_link') }}
              </router-link>
            </div>

            <div v-if="lowStockItems.length > 0" class="space-y-2">
              <div
                v-for="it in lowStockItems"
                :key="it.id"
                class="p-2.5 bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between text-xs"
              >
                <div>
                  <div class="font-bold text-slate-900 dark:text-white">{{ it.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">{{ it.code || '—' }}</div>
                </div>
                <div class="text-end font-mono">
                  <div class="font-black text-rose-400">{{ it.current_stock }} {{ it.unit }}</div>
                  <div class="text-[10px] text-slate-500">{{ $t('dashboard.min_limit_label') }} {{ it.min_stock }}</div>
                </div>
              </div>
            </div>

            <div v-else class="p-6 text-center text-xs text-emerald-400 font-bold">
              {{ $t('dashboard.all_items_safe_radar') }}
            </div>
          </div>

          <!-- Recent Invoices (Col 2) -->
          <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-3">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
              <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <FileText class="w-4 h-4 text-blue-400" />
                <span>{{ $t('dashboard.recent_invoices_today') }}</span>
              </h2>
              <router-link to="/invoices" class="text-[11px] text-amber-400 hover:underline">
                {{ $t('dashboard.invoices_log_link') }}
              </router-link>
            </div>

            <div v-if="recentInvoices.length > 0" class="space-y-2">
              <div
                v-for="inv in recentInvoices"
                :key="inv.id"
                class="p-2.5 bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between text-xs"
              >
                <div>
                  <div class="font-bold text-theme-primary font-mono">{{ inv.invoice_number }}</div>
                  <div class="text-[10px] text-slate-300 font-sans">{{ inv.customer_name }}</div>
                </div>
                <div class="text-end font-mono">
                  <div class="font-black text-white">{{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}</div>
                  <div class="text-[10px] text-slate-400">{{ inv.created_at }}</div>
                </div>
              </div>
            </div>

            <div v-else class="p-6 text-center text-xs text-slate-500 font-bold">
              {{ $t('dashboard.no_invoices_today_sub') }}
            </div>
          </div>

          <!-- Top Selling Items (Col 3) -->
          <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-lg space-y-3">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
              <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <PackageCheck class="w-4 h-4 text-amber-400" />
                <span>{{ $t('dashboard.top_selling_month') }}</span>
              </h2>
              <router-link to="/reports" class="text-[11px] text-amber-400 hover:underline">
                {{ $t('dashboard.profitability_reports_link') }}
              </router-link>
            </div>

            <div v-if="topSellingItems.length > 0" class="space-y-2">
              <div
                v-for="top in topSellingItems"
                :key="top.item_id"
                class="p-2.5 bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between text-xs"
              >
                <div>
                  <div class="font-bold text-slate-900 dark:text-white">{{ top.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">{{ top.code || '—' }}</div>
                </div>
                <div class="text-end font-mono">
                  <div class="font-black text-cyan-400">{{ top.total_qty }} {{ top.unit }}</div>
                  <div class="text-[10px] text-slate-400">{{ formatMoney(top.total_revenue) }} {{ $t('common.currency') }}</div>
                </div>
              </div>
            </div>

            <div v-else class="p-6 text-center text-xs text-slate-500 font-bold">
              {{ $t('dashboard.no_top_items_month') }}
            </div>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import {
    ShoppingCart,
    Layers,
    PackageCheck,
    TrendingUp,
    BarChart3,
    Users,
    Wallet,
    AlertOctagon,
    FileText
} from 'lucide-vue-next';

const authStore = useAuthStore();
const dashboardData = ref(null);
const isLoading = ref(false);

let pollInterval = null;

const metrics = computed(() => dashboardData.value?.metrics || {});
const trendDays = computed(() => dashboardData.value?.analytics?.daily_sales || []);
const lowStockItems = computed(() => dashboardData.value?.low_stock_items || []);
const recentInvoices = computed(() => dashboardData.value?.recent_invoices || []);
const topSellingItems = computed(() => dashboardData.value?.top_selling_items || []);

const maxTrendSales = computed(() => {
    if (!trendDays.value.length) return 1;
    const max = Math.max(...trendDays.value.map(d => parseFloat(d.total_sales) || 0));
    return max > 0 ? max : 1;
});

const getBarWidth = (sales) => {
    const val = parseFloat(sales) || 0;
    const pct = (val / maxTrendSales.value) * 100;
    return Math.max(pct, 4); // minimum 4% width for visibility
};

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchDashboard = async (isBackground = false) => {
    if (!isBackground) {
        isLoading.value = true;
    }
    try {
        const res = await api.get('/dashboard/summary');
        dashboardData.value = res.data?.data || {};
    } catch (e) {
        console.error('Failed to fetch dashboard data:', e);
    } finally {
        if (!isBackground) {
            isLoading.value = false;
        }
    }
};

onMounted(() => {
    fetchDashboard(false);
    // Background polling every 30 seconds
    pollInterval = setInterval(() => {
        fetchDashboard(true);
    }, 30000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>
