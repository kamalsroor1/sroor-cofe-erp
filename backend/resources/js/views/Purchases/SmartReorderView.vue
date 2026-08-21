<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <router-link
            to="/purchases"
            class="w-10 h-10 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-sm border border-slate-700 shrink-0"
            :title="$t('common.back')"
          >
            <ArrowRight class="w-5 h-5" />
          </router-link>
          <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
              <span>رادار إعادة الطلب الذكي</span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                AI Powered ⚡
              </span>
            </h1>
            <p class="text-xs text-slate-400 font-bold">
              تحليل استهلاك الخامات ومعدل السحب اليومي وتوقع تاريخ نفاد المخزون
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="exportToPurchaseOrder"
            :disabled="selectedItems.length === 0"
            class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-lg shadow-amber-500/20 disabled:opacity-40 cursor-pointer"
          >
            <ShoppingCart class="w-4 h-4" />
            <span>إنشاء أمر شراء مجمع ({{ selectedItems.length }})</span>
          </button>
        </div>
      </div>

      <!-- Urgency & Financial Radar Metrics -->
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <!-- Critical Items -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-rose-500/30 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">نواقص حرجة (0 - 3 أيام)</span>
            <AlertTriangle class="w-4 h-4 text-rose-400" />
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ metrics.critical_count || 0 }} <span class="text-xs text-slate-400">صنف</span>
          </div>
          <span class="text-[10px] text-slate-500">عرضة للنفاد الفوري خلال 72 ساعة</span>
        </div>

        <!-- Warning Items -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-amber-500/30 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">تنبيه توريد (4 - 7 أيام)</span>
            <Clock class="w-4 h-4 text-amber-400" />
          </div>
          <div class="text-2xl font-black text-amber-400 font-mono">
            {{ metrics.warning_count || 0 }} <span class="text-xs text-slate-400">صنف</span>
          </div>
          <span class="text-[10px] text-slate-500">بحاجة للتواصل مع الموردين</span>
        </div>

        <!-- Safe Items -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-emerald-500/30 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">رصيد آمن (+8 أيام)</span>
            <ShieldCheck class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="text-2xl font-black text-emerald-400 font-mono">
            {{ metrics.safe_count || 0 }} <span class="text-xs text-slate-400">صنف</span>
          </div>
          <span class="text-[10px] text-slate-500">المخزون يكفي الفترة المحددة</span>
        </div>

        <!-- Estimated Total Cost -->
        <div class="p-4 rounded-2xl bg-slate-950/80 border border-purple-500/30 shadow-md space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">التكلفة التقديرية لإعادة التغذية</span>
            <Sparkles class="w-4 h-4 text-purple-400" />
          </div>
          <div class="text-xl font-black text-purple-400 font-mono">
            {{ formatMoney(metrics.total_estimated_cost || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <span class="text-[10px] text-slate-500">لتغطية الاستهلاك المطلوب</span>
        </div>
      </div>

      <!-- Filter Controls Bar -->
      <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceFetch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none"
            placeholder="بحث عن صنف أو خامة..."
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Analysis Days -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-400 whitespace-nowrap">تحليل استهلاك:</span>
          <select
            v-model="analysisDays"
            @change="fetchSuggestions"
            class="h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option :value="7">آخر 7 أيام</option>
            <option :value="14">آخر 14 يوم (موصى به)</option>
            <option :value="30">آخر 30 يوم</option>
          </select>
        </div>

        <!-- Target Cover Days -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-400 whitespace-nowrap">فترة التغطية المطلوبة:</span>
          <select
            v-model="targetCoverDays"
            @change="fetchSuggestions"
            class="h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option :value="7">تغطية 7 أيام</option>
            <option :value="15">تغطية 15 يوم</option>
            <option :value="30">تغطية شهر كامل</option>
          </select>
        </div>

        <!-- Urgency Pill Filter -->
        <div class="w-full md:w-36">
          <select
            v-model="selectedUrgency"
            @change="fetchSuggestions"
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
          >
            <option value="all">كافة المستويات</option>
            <option value="critical">🚨 حرجة فقط</option>
            <option value="warning">⚠️ تنبيه فقط</option>
            <option value="safe">✅ آمن فقط</option>
          </select>
        </div>
      </div>

      <!-- Reorder Suggestions Table -->
      <div class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="suggestions.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 border-b border-slate-800">
                <th class="p-3.5 text-center w-10">
                  <input
                    type="checkbox"
                    @change="toggleSelectAll"
                    :checked="isAllSelected"
                    class="rounded border-slate-700 text-amber-500 focus:ring-0 cursor-pointer"
                  >
                </th>
                <th class="py-3 px-4 text-start font-bold">الصنف / الكود</th>
                <th class="py-3 px-4 text-end font-bold">الرصيد الحالي</th>
                <th class="py-3 px-4 text-end font-bold">السحب اليومي</th>
                <th class="py-3 px-4 text-center font-bold">الرصيد يكفي لمدة</th>
                <th class="py-3 px-4 text-end font-bold">الكمية المقترحة للطلب</th>
                <th class="py-3 px-4 text-end font-bold">التكلفة التقديرية</th>
                <th class="py-3 px-4 text-center font-bold">مستوى الخطورة</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
              <tr
                v-for="it in suggestions"
                :key="it.id"
                class="hover:bg-slate-900/50 transition-colors"
                :class="it.urgency === 'critical' ? 'bg-rose-500/5' : ''"
              >
                <td class="p-3.5 text-center">
                  <input
                    type="checkbox"
                    :value="it"
                    v-model="selectedItems"
                    class="rounded border-slate-700 text-amber-500 focus:ring-0 cursor-pointer"
                  >
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-white font-tajawal text-sm">{{ it.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ it.code || '—' }} ({{ it.unit }})</div>
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-sm" :class="it.current_stock <= 0 ? 'text-rose-500' : 'text-slate-200'">
                  {{ it.current_stock }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono text-slate-400">
                  {{ it.avg_daily_consumption || '0.00' }} / يوم
                </td>
                <td class="py-3.5 px-4 text-center font-mono font-bold" :class="it.days_remaining <= 3 ? 'text-rose-400' : 'text-amber-400'">
                  {{ it.days_remaining !== null ? `${it.days_remaining} يوم` : 'غير محدد' }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-amber-400 text-sm">
                  {{ it.suggested_reorder_qty }} {{ it.unit }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-400">
                  {{ formatMoney(it.estimated_cost || 0) }} ج.م
                </td>
                <td class="py-3.5 px-4 text-center font-tajawal">
                  <span
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="getUrgencyBadge(it.urgency)"
                  >
                    {{ getUrgencyText(it.urgency) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :title="'لا توجد نواقص في المخزون حالياً'"
          :description="'كافة الأصناف والمخازن تتمتع بمستويات رصيد آمنة وفقاً لمعدل السحب الحالي.'"
          :icon="'✨'"
        />
      </div>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import api from '../../services/api';
import {
    ArrowRight,
    Sparkles,
    ShoppingCart,
    AlertTriangle,
    Clock,
    ShieldCheck,
    Search
} from 'lucide-vue-next';

const router = useRouter();

const suggestions = ref([]);
const metrics = ref({
    critical_count: 0,
    warning_count: 0,
    safe_count: 0,
    total_estimated_cost: 0,
});

const analysisDays = ref(14);
const targetCoverDays = ref(15);
const selectedUrgency = ref('all');
const searchQuery = ref('');
const isLoading = ref(false);
const selectedItems = ref([]);

let debounceTimer = null;

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getUrgencyBadge = (urgency) => {
    switch (urgency) {
        case 'critical':
            return 'bg-rose-500/10 border-rose-500/30 text-rose-400';
        case 'warning':
            return 'bg-amber-500/10 border-amber-500/30 text-amber-400';
        default:
            return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
    }
};

const getUrgencyText = (urgency) => {
    switch (urgency) {
        case 'critical':
            return '🚨 حرج فوري';
        case 'warning':
            return '⚠️ تنبيه وشيك';
        default:
            return '✅ رصيد آمن';
    }
};

const fetchSuggestions = async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/purchases/smart-reorder', {
            params: {
                analysis_days: analysisDays.value,
                target_cover_days: targetCoverDays.value,
                urgency: selectedUrgency.value !== 'all' ? selectedUrgency.value : undefined,
                search: searchQuery.value || undefined,
            },
        });
        const data = response.data?.data;
        if (data) {
            suggestions.value = data.suggestions || [];
            metrics.value = {
                critical_count: data.critical_count || 0,
                warning_count: data.warning_count || 0,
                safe_count: data.safe_count || 0,
                total_estimated_cost: data.total_estimated_cost || 0,
            };
        }
    } catch (error) {
        console.error('Failed to load smart reorder suggestions:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchSuggestions, 300);
};

const isAllSelected = computed(() => {
    return suggestions.value.length > 0 && selectedItems.value.length === suggestions.value.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedItems.value = [];
    } else {
        selectedItems.value = [...suggestions.value];
    }
};

const exportToPurchaseOrder = () => {
    if (selectedItems.value.length === 0) return;
    const prefill = JSON.stringify(selectedItems.value.map(it => ({
        item_id: it.id,
        quantity: it.suggested_reorder_qty,
        cost_price: it.cost_price,
    })));
    router.push({
        path: '/purchases/create',
        query: { prefill },
    });
};

onMounted(fetchSuggestions);
</script>
