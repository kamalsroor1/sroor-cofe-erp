<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
      <!-- Header & Action Bar -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 no-print">
        <div class="space-y-1">
          <div class="flex items-center gap-3">
            <router-link
              to="/items"
              class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-300 dark:border-slate-700 shrink-0"
              :title="$t('common.back')"
            >
              <ArrowRight class="w-5 h-5" />
            </router-link>
            <div>
              <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-1.5">
                <span>{{ $t('inventory.movements_title') }}:</span>
                <span class="text-amber-400">{{ item?.name }}</span>
                <span v-if="item?.code" class="text-xs font-mono text-slate-400 bg-slate-800 px-2 py-0.5 rounded-md">
                  {{ item.code }}
                </span>
              </h1>
              <p class="text-xs text-slate-400 font-bold">
                {{ $t('inventory.movements_subtitle') }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto">
          <button
            type="button"
            @click="printReport"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-900 dark:text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm cursor-pointer"
          >
            <Printer class="w-4 h-4 text-amber-400" />
            <span>{{ $t('common.print') }}</span>
          </button>
        </div>
      </div>

      <!-- Item Financial & Movement Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <!-- Total In (الوارد) -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="text-xs font-bold text-slate-400">{{ $t('inventory.total_in') }} {{ $t('inventory.total_in_sub') }}</div>
          <div class="text-xl font-black text-emerald-400 font-mono">
            +{{ formatQty(stats.total_in || 0) }} <span class="text-xs font-normal text-slate-400">{{ item?.unit }}</span>
          </div>
        </div>

        <!-- Total Out (المنصرف) -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="text-xs font-bold text-slate-400">{{ $t('inventory.total_out') }} {{ $t('inventory.total_out_sub') }}</div>
          <div class="text-xl font-black text-rose-400 font-mono">
            -{{ formatQty(stats.total_out || 0) }} <span class="text-xs font-normal text-slate-400">{{ item?.unit }}</span>
          </div>
        </div>

        <!-- Net Movement (صافي الحركة) -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="text-xs font-bold text-slate-400">{{ $t('inventory.net_movement') }}</div>
          <div
            class="text-xl font-black font-mono"
            :class="stats.net_movement >= 0 ? 'text-cyan-400' : 'text-amber-400'"
          >
            {{ stats.net_movement > 0 ? '+' : '' }}{{ formatQty(stats.net_movement || 0) }} <span class="text-xs font-normal text-slate-400">{{ item?.unit }}</span>
          </div>
        </div>

        <!-- Current Stock (الرصيد الفعلي) -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
          <div class="text-xs font-bold text-slate-400">{{ $t('inventory.current_stock') }}</div>
          <div class="text-xl font-black text-slate-900 dark:text-white font-mono">
            {{ formatQty(stats.current_scope_stock || item?.current_stock || 0) }} <span class="text-xs font-normal text-slate-400">{{ item?.unit }}</span>
          </div>
        </div>
      </div>

      <!-- Date Range & Movement Type Filter Bar -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 no-print">
        <!-- Date Inputs -->
        <div class="flex items-center gap-2 flex-wrap">
          <div class="flex items-center gap-1.5">
            <span class="text-xs font-bold text-slate-400">{{ $t('common.from') }}:</span>
            <input
              v-model="dateFrom"
              type="date"
              class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            >
          </div>
          <div class="flex items-center gap-1.5">
            <span class="text-xs font-bold text-slate-400">{{ $t('common.to') }}:</span>
            <input
              v-model="dateTo"
              type="date"
              class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
            >
          </div>
          <button
            type="button"
            @click="fetchMovements"
            class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black transition-all cursor-pointer"
          >
            {{ $t('common.filter') }}
          </button>
        </div>

        <!-- Quick Date Range Pills -->
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-800 overflow-x-auto">
          <button
            type="button"
            @click="applyPreset('today')"
            class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
            :class="activePreset === 'today' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-400 hover:text-white'"
          >
            {{ $t('common.today') }}
          </button>
          <button
            type="button"
            @click="applyPreset('this_month')"
            class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
            :class="activePreset === 'this_month' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-400 hover:text-white'"
          >
            {{ $t('common.this_month') }}
          </button>
          <button
            type="button"
            @click="applyPreset('all')"
            class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
            :class="activePreset === 'all' ? 'bg-theme-primary text-white shadow-theme-primary font-black' : 'text-slate-400 hover:text-white'"
          >
            {{ $t('common.all') }}
          </button>
        </div>
      </div>

      <!-- Movements Ledger Table -->
      <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="movements.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.movement_type') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.reference_no') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('common.quantity') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.stock_before') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.stock_after') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.store_user') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
              <tr
                v-for="(row, idx) in movements"
                :key="row.id || idx"
                class="hover:bg-slate-50 dark:hover:bg-slate-100 dark:hover:bg-slate-900/50 transition-colors"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4 font-mono text-slate-300">{{ row.created_at }}</td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">
                  <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold border" :class="getMovementBadge(row.movement_type)">
                    {{ formatMovementLabel(row.movement_type) }}
                  </span>
                </td>
                <td class="py-3.5 px-4 font-mono text-amber-400">{{ row.document_number || '—' }}</td>
                <td class="py-3.5 px-4 text-end font-mono font-bold" :class="isPositiveMovement(row.movement_type) ? 'text-emerald-400' : 'text-rose-400'">
                  {{ isPositiveMovement(row.movement_type) ? '+' : '-' }}{{ formatQty(row.quantity) }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono text-slate-400">
                  {{ formatQty(row.stock_before) }}
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-black text-white">
                  {{ formatQty(row.stock_after) }}
                </td>
                <td class="py-3.5 px-4 font-tajawal text-slate-300">
                  <div>{{ row.store?.name || $t('common.main_branch') }}</div>
                  <div class="text-[10px] text-slate-500">{{ row.user?.name || $t('common.system') }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :title="$t('inventory.no_movements_found')"
          :description="$t('inventory.no_movements_description')"
          :icon="'📦'"
        />
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import EmptyState from '../../Components/Common/EmptyState.vue';
import api from '../../services/api';
import { trans } from '../../helpers/trans';
import {
    ArrowRight,
    Printer
} from 'lucide-vue-next';

const route = useRoute();
const itemId = route.params.id;

const item = ref(null);
const movements = ref([]);
const stats = ref({
    total_in: 0,
    total_out: 0,
    net_movement: 0,
    current_scope_stock: 0,
});

const dateFrom = ref('');
const dateTo = ref('');
const activePreset = ref('all');
const isLoading = ref(false);

const formatQty = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
};

const formatMovementLabel = (type) => {
    const map = {
        purchase_in: `🚛 ${trans('inventory.movement_purchase')}`,
        sales_out: `🛒 ${trans('inventory.movement_sale')}`,
        transfer_in: `📥 ${trans('inventory.movement_transfer_in')}`,
        transfer_out: `📤 ${trans('inventory.movement_transfer_out')}`,
        sales_return_in: `↩️ ${trans('inventory.movement_sale_return')}`,
        purchase_return_out: `↪️ ${trans('inventory.movement_purchase_return')}`,
        stock_adjustment_in: `➕ ${trans('inventory.movement_adjustment')}`,
        stock_adjustment_out: `➖ ${trans('inventory.movement_adjustment')}`,
        cancellation_in: `🚫 ${trans('invoices.cancelled_badge')}`,
        waste_out: `🗑️ ${trans('inventory.movement_waste')}`,
        stock_deposit_in: `📦 ${trans('inventory.movement_initial')}`,
    };
    return map[type] || type;
};

const isPositiveMovement = (type) => {
    const positive = ['purchase_in', 'stock_deposit_in', 'stock_adjustment_in', 'cancellation_in', 'transfer_in', 'sales_return_in'];
    return positive.includes(type);
};

const getMovementBadge = (type) => {
    return isPositiveMovement(type)
        ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
        : 'bg-rose-500/10 border-rose-500/30 text-rose-400';
};

const applyPreset = (preset) => {
    activePreset.value = preset;
    const now = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        dateFrom.value = formatDate(now);
        dateTo.value = formatDate(now);
    } else if (preset === 'this_month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        dateFrom.value = formatDate(start);
        dateTo.value = formatDate(end);
    } else if (preset === 'all') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    fetchMovements();
};

const fetchMovements = async () => {
    isLoading.value = true;
    try {
        const response = await api.get(`/items/${itemId}/movements`, {
            params: {
                from_date: dateFrom.value || undefined,
                to_date: dateTo.value || undefined,
            },
        });
        const data = response.data?.data;
        if (data) {
            item.value = data.item;
            movements.value = data.data || [];
            stats.value = data.stats || {};
        }
    } catch (error) {
        console.error('Failed to load item movements:', error);
    } finally {
        isLoading.value = false;
    }
};

const printReport = () => {
    window.print();
};

onMounted(fetchMovements);
</script>

<style scoped>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>
