<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-950/80 p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
            <Crown class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-xl font-black text-white">لوحة تحكم السوبر أدمن (إدارة المنصة المركزية)</h1>
            <p class="text-xs text-slate-400">متابعة المستأجرين، الاشتراكات الشهرية MRR، وتوزيع الباقات</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <router-link
            to="/super-admin/tenants"
            class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition cursor-pointer"
          >
            <Building2 class="w-4 h-4" />
            <span>إدارة المستأجرين</span>
          </router-link>

          <router-link
            to="/super-admin/plans"
            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 font-bold text-xs rounded-xl shadow flex items-center gap-2 transition"
          >
            <Layers class="w-4 h-4 text-amber-400" />
            <span>إدارة الباقات والأسعار</span>
          </router-link>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-16 text-center">
        <div class="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-slate-400">جاري تحميل مؤشرات المنصة المركزية...</p>
      </div>

      <div v-else class="space-y-6">
        <!-- 4 Platform Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-1">
            <span class="text-slate-400 text-xs font-bold">إجمالي المستأجرين</span>
            <div class="text-2xl font-black text-white font-mono">{{ metrics.total_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">حسابات المنصة</div>
          </div>

          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-1">
            <span class="text-emerald-400 text-xs font-bold">المستأجرون النشطون</span>
            <div class="text-2xl font-black text-emerald-400 font-mono">{{ metrics.active_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">اشتراكات مفعلة</div>
          </div>

          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-1">
            <span class="text-amber-400 text-xs font-bold">الفترة التجريبية</span>
            <div class="text-2xl font-black text-amber-400 font-mono">{{ metrics.trial_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">قيد التجربة (Trial)</div>
          </div>

          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-1">
            <span class="text-rose-400 text-xs font-bold">حسابات موقوفة</span>
            <div class="text-2xl font-black text-rose-400 font-mono">{{ metrics.suspended_tenants || 0 }}</div>
            <div class="text-[10px] text-slate-500">معطلة أو منتهية</div>
          </div>

          <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-1 sm:col-span-2 lg:col-span-1">
            <span class="text-purple-400 text-xs font-bold">الدخل الشهري (MRR)</span>
            <div class="text-2xl font-black text-purple-400 font-mono">{{ formatMoney(metrics.mrr || 0) }} <span class="text-xs text-slate-400">ج.م</span></div>
            <div class="text-[10px] text-slate-500">Monthly Recurring Revenue</div>
          </div>
        </div>

        <!-- Plans Distribution & Recent Tenants (2 Cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Plans Distribution (Col 1) -->
          <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
              <h2 class="text-xs font-bold text-slate-300 flex items-center gap-2">
                <Layers class="w-4 h-4 text-amber-400" />
                <span>توزيع الباقات والاشتراكات</span>
              </h2>
            </div>

            <div v-if="planStats.length > 0" class="space-y-3">
              <div
                v-for="p in planStats"
                :key="p.id"
                class="p-3 bg-slate-900/60 border border-slate-800 rounded-xl flex items-center justify-between text-xs"
              >
                <div>
                  <div class="font-bold text-white">{{ p.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">{{ formatMoney(p.price_monthly) }} ج.م / شهرياً</div>
                </div>
                <div class="text-end font-mono">
                  <span class="px-2.5 py-1 bg-purple-500/10 border border-purple-500/30 text-purple-400 rounded-full font-bold">
                    {{ p.tenants_count }} مشترك
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Tenants (Col 2-3) -->
          <div class="lg:col-span-2 bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
              <h2 class="text-xs font-bold text-slate-300 flex items-center gap-2">
                <Building2 class="w-4 h-4 text-purple-400" />
                <span>أحدث المستأجرين المسجلين</span>
              </h2>
              <router-link to="/super-admin/tenants" class="text-[11px] text-amber-400 hover:underline">
                عرض جميع المستأجرين ←
              </router-link>
            </div>

            <div v-if="recentTenants.length > 0" class="space-y-2">
              <div
                v-for="t in recentTenants"
                :key="t.id"
                class="p-3 bg-slate-900/60 border border-slate-800 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs"
              >
                <div class="space-y-0.5">
                  <div class="font-bold text-white flex items-center gap-2">
                    <span>{{ t.name }}</span>
                    <span class="text-[10px] text-slate-400 font-mono">({{ t.domain }})</span>
                  </div>
                  <div class="text-[10px] text-slate-400">الباقة: <span class="text-amber-400 font-bold">{{ t.plan_name }}</span></div>
                </div>

                <div class="flex items-center gap-3 self-end sm:self-center">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="getStatusBadgeClass(t.status)"
                  >
                    {{ getStatusLabel(t.status) }}
                  </span>
                  <span class="text-[10px] text-slate-500 font-mono">{{ t.created_at }}</span>
                </div>
              </div>
            </div>

            <div v-else class="p-8 text-center text-xs text-slate-500">
              لا يوجد مستأجرون مسجلون بعد.
            </div>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import {
    Crown,
    Building2,
    Layers
} from 'lucide-vue-next';

const metrics = ref({});
const planStats = ref([]);
const recentTenants = ref([]);
const isLoading = ref(false);

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
        case 'trial': return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
        case 'suspended': return 'bg-rose-500/10 border-rose-500/30 text-rose-400';
        default: return 'bg-slate-500/10 border-slate-500/30 text-slate-400';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'active': return 'نشط ✅';
        case 'trial': return 'تجريبي ⏳';
        case 'suspended': return 'موقوف 🚫';
        default: return status;
    }
};

const fetchDashboard = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/super-admin/dashboard');
        metrics.value = res.data?.metrics || {};
        planStats.value = res.data?.plan_stats || [];
        recentTenants.value = res.data?.recent_tenants || [];
    } catch (e) {
        console.error('Failed to load super admin dashboard:', e);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchDashboard();
});
</script>
