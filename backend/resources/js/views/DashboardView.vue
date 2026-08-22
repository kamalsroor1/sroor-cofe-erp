<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 🌟 TOP WELCOME HEADER BANNER (Identical to reference image) -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="p-6 rounded-3xl bg-white dark:bg-gradient-to-r dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
          مرحباً بك في نظام {{ appConfigStore.companyName || 'سرور' }} لإدارة الفواتير
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-bold mt-1">
          نظرة عامة على المبيعات، رصيد الخزينة، المخزون، وحسابات العملاء
        </p>
      </div>

      <!-- Quick Action Buttons in Banner -->
      <div class="flex flex-wrap items-center gap-3 shrink-0">
        <router-link
          to="/pos"
          class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-500/20 flex items-center gap-2 transition-all active:scale-95 cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>فاتورة بيع سريعة (POS)</span>
        </router-link>

        <router-link
          to="/purchases/create"
          class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition-all active:scale-95 cursor-pointer"
        >
          <ShoppingCart class="w-4 h-4 text-slate-500 dark:text-slate-400" />
          <span>فاتورة شراء (توريد)</span>
        </router-link>
      </div>
    </div>

    <!-- Loading Spinner -->
    <div v-if="isLoading && !dashboardData" class="p-20 text-center">
      <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-xs text-slate-400 font-bold">جاري تحديث لوحة البيانات الحية...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- 📊 4 KEY KPI METRIC CARDS (Exact match to reference image) -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. مبيعات اليوم (Green Theme) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مبيعات اليوم</span>
            <div class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold text-sm">
              $
            </div>
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
            {{ formatMoney(metrics.today_sales || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold font-tajawal">
            {{ metrics.today_invoices_count || 0 }} فاتورة معتمدة اليوم
          </div>
        </div>

        <!-- 2. مجمل أرباح الشهر (Cyan/Teal Theme) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مجمل أرباح الشهر</span>
            <div class="w-8 h-8 rounded-full bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
              <TrendingUp class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-cyan-500 dark:text-cyan-400 font-mono">
            {{ formatMoney(metrics.monthly_gross_profit || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">
            هامش ربح: <span class="font-mono text-emerald-500 font-black">%{{ metrics.monthly_margin || '0.00' }}</span>
          </div>
        </div>

        <!-- 3. إجمالي ديون العملاء (الآجل) (Amber/Gold Theme) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">إجمالي ديون العملاء (الآجل)</span>
            <div class="w-8 h-8 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center">
              <CreditCard class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-amber-500 dark:text-amber-400 font-mono">
            {{ formatMoney(metrics.customers_debt || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">
            مستحقات واجبة التحصيل
          </div>
        </div>

        <!-- 4. مبيعات الشهر الحالي (Indigo/Purple Theme) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">مبيعات الشهر الحالي</span>
            <div class="w-8 h-8 rounded-full bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
              <BarChart3 class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono">
            {{ formatMoney(metrics.monthly_sales || 0) }} <span class="text-xs font-sans text-slate-400 font-bold">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">
            إجمالي تعاملات الشهر
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- 🔄 SPLIT MAIN GRID: Recent Invoices (70%) + Low Stock (30%)  -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- 🧾 RIGHT COLUMN (~70%): آخر فواتير المبيعات الصادرة -->
        <div class="lg:col-span-8 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <h2 class="text-sm font-black text-slate-900 dark:text-white">آخر فواتير المبيعات الصادرة</h2>
            </div>
            <router-link
              to="/invoices"
              class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-theme-primary transition flex items-center gap-1 cursor-pointer"
            >
              <span>عرض الكل</span>
              <span>←</span>
            </router-link>
          </div>

          <!-- Invoices Table -->
          <div class="overflow-x-auto">
            <table class="w-full text-start text-xs font-tajawal">
              <thead class="text-slate-400 text-[11px] font-bold border-b border-slate-200 dark:border-slate-800/80">
                <tr>
                  <th class="py-3 text-start">رقم الفاتورة</th>
                  <th class="py-3 text-start">العميل</th>
                  <th class="py-3 text-start">التاريخ</th>
                  <th class="py-3 text-start">الإجمالي</th>
                  <th class="py-3 text-center">الحالة</th>
                  <th class="py-3 text-end">إجراءات</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
                <tr
                  v-for="inv in recentInvoices"
                  :key="inv.id"
                  class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors"
                >
                  <!-- رقم الفاتورة -->
                  <td class="py-3.5 text-cyan-600 dark:text-cyan-400 font-bold font-mono">
                    {{ inv.invoice_number }}
                  </td>

                  <!-- العميل -->
                  <td class="py-3.5 font-sans font-bold text-slate-800 dark:text-slate-200">
                    {{ inv.customer_name }}
                  </td>

                  <!-- التاريخ -->
                  <td class="py-3.5 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                    {{ inv.invoice_date || inv.created_at }}
                  </td>

                  <!-- الإجمالي -->
                  <td class="py-3.5 font-bold text-slate-900 dark:text-white font-mono">
                    {{ formatMoney(inv.net_total) }} <span class="text-[10px] font-sans text-slate-400">ج.م</span>
                  </td>

                  <!-- الحالة -->
                  <td class="py-3.5 text-center font-sans">
                    <span
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                      :class="getInvoiceStatusBadge(inv)"
                    >
                      {{ getInvoiceStatusLabel(inv) }}
                    </span>
                  </td>

                  <!-- إجراءات -->
                  <td class="py-3.5 text-end font-sans">
                    <button
                      type="button"
                      @click="previewInvoice(inv)"
                      class="px-3 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-[11px] font-bold transition cursor-pointer border border-slate-300 dark:border-slate-700 active:scale-95"
                    >
                      معاينة / طباعة
                    </button>
                  </td>
                </tr>

                <tr v-if="recentInvoices.length === 0">
                  <td colspan="6" class="py-12 text-center text-xs text-slate-400 font-bold font-sans">
                    لا توجد فواتير مبيعات مسجلة حتى الآن
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ⚠️ LEFT COLUMN (~30%): تنبيهات النواقص بالمخزن -->
        <div class="lg:col-span-4 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl space-y-4 flex flex-col justify-between">
          <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <h2 class="text-sm font-black text-slate-900 dark:text-white">تنبيهات النواقص بالمخزن</h2>
              </div>
              <router-link
                to="/smart-reorder"
                class="text-xs font-bold text-amber-500 hover:text-amber-400 transition flex items-center gap-1 cursor-pointer"
              >
                <span>مساعد المشتريات</span>
                <span>←</span>
              </router-link>
            </div>

            <!-- Low Stock Items List -->
            <div class="space-y-2 max-h-[460px] overflow-y-auto pr-1">
              <div
                v-for="item in lowStockItems"
                :key="item.id"
                class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-rose-500/30 transition flex items-center justify-between gap-3 group"
              >
                <!-- Right: Name & Code -->
                <div class="min-w-0 flex-1">
                  <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-amber-500 transition">
                    {{ item.name }}
                  </div>
                  <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                    كود: {{ item.code || `ITM-${item.id}` }}
                  </div>
                </div>

                <!-- Left: Stock Badge & Limit -->
                <div class="text-end shrink-0">
                  <div class="text-xs font-black text-rose-500 font-mono">
                    {{ formatQty(item.current_stock) }} {{ item.unit }}
                  </div>
                  <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                    الحد الأدنى: {{ formatQty(item.min_stock_level || 5) }}
                  </div>
                </div>
              </div>

              <div v-if="lowStockItems.length === 0" class="py-12 text-center text-xs text-slate-400 font-bold">
                ✓ جميع الأصناف متوفرة بمستويات آمنة
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import api from '../services/api';
import {
    Plus,
    ShoppingCart,
    TrendingUp,
    CreditCard,
    BarChart3,
} from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();

const dashboardData = ref(null);
const isLoading = ref(true);

const metrics = computed(() => dashboardData.value?.metrics || {});
const recentInvoices = computed(() => dashboardData.value?.recent_invoices || []);
const lowStockItems = computed(() => dashboardData.value?.low_stock_items || []);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatQty = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getInvoiceStatusBadge = (inv) => {
    if (inv.status === 'cancelled') {
        return 'bg-rose-500/10 text-rose-500 border-rose-500/30';
    }
    if (inv.remaining_amount > 0) {
        return 'bg-amber-500/10 text-amber-500 border-amber-500/30';
    }
    return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30';
};

const getInvoiceStatusLabel = (inv) => {
    if (inv.status === 'cancelled') return 'ملغاة';
    if (inv.remaining_amount > 0) return 'آجل';
    return 'مدفوعة';
};

const previewInvoice = (inv) => {
    router.push(`/invoices?view=${inv.id}`);
};

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

onMounted(() => {
    fetchDashboard();
});
</script>
