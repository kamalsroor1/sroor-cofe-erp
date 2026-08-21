<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto">
      <!-- Welcome Banner -->
      <div class="p-6 rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border border-slate-800 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full text-xs font-bold text-amber-400 mb-2 font-tajawal">
            <span>✨ Pure API Architecture (SPA Mode)</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-white font-tajawal tracking-tight">
            أهلاً بك، {{ authStore.userName }} 👋
          </h1>
          <p class="text-xs sm:text-sm text-slate-400 font-bold mt-1 font-tajawal">
            لوحة المتابعة اللحظية لفرع: <span class="text-amber-400">{{ authStore.activeStoreName }}</span>
          </p>
        </div>

        <div class="flex items-center gap-3">
          <a
            href="/pos"
            class="px-5 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-slate-950 font-black text-xs sm:text-sm rounded-2xl shadow-lg shadow-emerald-500/20 flex items-center gap-2 transition-all cursor-pointer font-tajawal"
          >
            <ShoppingCart class="w-4 h-4" />
            <span>نقطة البيع (POS)</span>
          </a>
        </div>
      </div>

      <!-- Quick Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Sales Card -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">مبيعات اليوم</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <TrendingUp class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-white font-mono">
            {{ formatMoney(summary?.today_sales || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            عدد الفواتير: <span class="font-mono text-slate-300">{{ summary?.today_invoices_count || 0 }}</span>
          </div>
        </div>

        <!-- Cash in Drawer -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">نقدية الدرج الحالية</span>
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
              <Wallet class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-amber-400 font-mono">
            {{ formatMoney(summary?.current_cash || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            الوردية: <span class="font-mono text-slate-300">{{ appConfigStore.currentShiftNumber || 'غير مفتوحة' }}</span>
          </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">النواقص وحد الطلب</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
              <AlertOctagon class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ summary?.low_stock_count || 0 }} <span class="text-xs text-slate-400">صنف</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            <a href="/purchases" class="text-amber-400 hover:underline">إعادة الطلب الذكي ←</a>
          </div>
        </div>

        <!-- Customer Debts -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">مديونيات العملاء</span>
            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
              <Users class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-cyan-400 font-mono">
            {{ formatMoney(summary?.customer_debts || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            مستحقات آجلة للتحصيل
          </div>
        </div>
      </div>

      <!-- Notifications & Action Center -->
      <div v-if="appConfigStore.notifications?.length > 0" class="space-y-3">
        <h2 class="text-sm font-black text-slate-300 font-tajawal flex items-center gap-2">
          <Bell class="w-4 h-4 text-amber-400" />
          <span>التنبيهات اللحظية ومؤشرات الرقابة</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div
            v-for="(alert, idx) in appConfigStore.notifications"
            :key="idx"
            class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 flex items-start justify-between gap-3 shadow-md"
          >
            <div class="flex items-start gap-3">
              <span class="text-xl shrink-0">{{ alert.icon }}</span>
              <div>
                <h3 class="text-xs font-bold text-white font-tajawal">{{ alert.title }}</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ alert.description }}</p>
              </div>
            </div>
            <a
              v-if="alert.link"
              :href="alert.link"
              class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-700 rounded-xl text-[11px] font-bold text-amber-400 whitespace-nowrap transition-colors"
            >
              {{ alert.link_label || 'عرض' }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import SpaLayout from '../Layouts/SpaLayout.vue';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import api from '../services/api';
import {
    ShoppingCart,
    TrendingUp,
    Wallet,
    AlertOctagon,
    Users,
    Bell
} from 'lucide-vue-next';

const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const summary = ref(null);
const isLoading = ref(false);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/dashboard/summary');
        summary.value = response.data?.data || {};
    } catch (e) {
        console.error('Failed to load dashboard summary:', e);
    } finally {
        isLoading.value = false;
    }
});
</script>
