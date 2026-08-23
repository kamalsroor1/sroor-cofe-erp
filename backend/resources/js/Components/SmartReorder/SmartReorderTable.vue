<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="6" :cols="7" />
    </div>

    <!-- Data Loaded -->
    <div v-else-if="suggestions.length > 0">
      <!-- 1. Desktop & Tablet Table (hidden on mobile) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-3.5 text-center w-12">
                <input
                  type="checkbox"
                  @change="$emit('toggle-select-all')"
                  :checked="isAllSelected"
                  class="rounded border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-0 cursor-pointer w-4 h-4"
                >
              </th>
              <th class="py-3.5 px-4 text-start font-bold">{{ $t('purchases.item_and_code') }}</th>
              <th class="py-3.5 px-4 text-end font-bold">{{ $t('inventory.current_stock') }}</th>
              <th class="py-3.5 px-4 text-end font-bold">{{ $t('purchases.daily_usage') }}</th>
              <th class="py-3.5 px-4 text-center font-bold">{{ $t('purchases.stock_lasts_for') }}</th>
              <th class="py-3.5 px-4 text-end font-bold">{{ $t('purchases.suggested_qty') }}</th>
              <th class="py-3.5 px-4 text-end font-bold">{{ $t('purchases.estimated_cost') }}</th>
              <th class="py-3.5 px-4 text-center font-bold">{{ $t('purchases.risk_level') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="it in suggestions"
              :key="it.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              :class="it.urgency === 'critical' ? 'bg-rose-500/5 dark:bg-rose-500/10' : ''"
            >
              <td class="p-3.5 text-center">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(it.id)"
                  @change="$emit('toggle-item', it)"
                  class="rounded border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-0 cursor-pointer w-4 h-4"
                >
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ it.name }}</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ it.code || '—' }} ({{ it.unit }})</div>
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black text-sm" :class="it.current_stock <= 0 ? 'text-rose-500' : 'text-slate-900 dark:text-slate-200'">
                {{ it.current_stock }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono text-slate-500 dark:text-slate-400">
                {{ it.avg_daily_consumption || '0.00' }} {{ $t('purchases.per_day') }}
              </td>
              <td class="py-3.5 px-4 text-center font-mono font-bold" :class="it.days_remaining <= 3 ? 'text-rose-500' : 'text-theme-primary'">
                {{ it.days_remaining !== null ? $t('purchases.days_count', { count: it.days_remaining }) : $t('purchases.not_specified') }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black text-theme-primary text-sm">
                {{ it.suggested_reorder_qty }} {{ it.unit }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-500 dark:text-emerald-400">
                {{ formatMoney(it.estimated_cost || 0) }} {{ $t('common.currency') }}
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

      <!-- 2. Mobile Responsive Tactile Cards (block md:hidden) -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="it in suggestions"
          :key="it.id"
          class="p-4 rounded-xl border transition-all"
          :class="[
            selectedIds.includes(it.id) ? 'border-theme-primary bg-theme-light/30 dark:bg-slate-800' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60',
            it.urgency === 'critical' ? 'bg-rose-500/5 border-rose-300 dark:border-rose-500/30' : ''
          ]"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <input
                type="checkbox"
                :checked="selectedIds.includes(it.id)"
                @change="$emit('toggle-item', it)"
                class="rounded border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-0 cursor-pointer w-5 h-5 min-w-[20px]"
              >
              <div>
                <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ it.name }}</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ it.code || '—' }} • {{ it.unit }}</p>
              </div>
            </div>

            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold border shrink-0"
              :class="getUrgencyBadge(it.urgency)"
            >
              {{ getUrgencyText(it.urgency) }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs font-mono">
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('inventory.current_stock') }}:</span>
              <span class="font-black" :class="it.current_stock <= 0 ? 'text-rose-500' : 'text-slate-800 dark:text-slate-200'">
                {{ it.current_stock }} {{ it.unit }}
              </span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('purchases.stock_lasts_for') }}:</span>
              <span class="font-bold" :class="it.days_remaining <= 3 ? 'text-rose-500' : 'text-theme-primary'">
                {{ it.days_remaining !== null ? $t('purchases.days_count', { count: it.days_remaining }) : $t('purchases.not_specified') }}
              </span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('purchases.suggested_qty') }}:</span>
              <span class="font-black text-theme-primary">{{ it.suggested_reorder_qty }} {{ it.unit }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('purchases.estimated_cost') }}:</span>
              <span class="font-black text-emerald-500">{{ formatMoney(it.estimated_cost || 0) }} {{ $t('common.currency') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('purchases.no_shortages_found_title')"
      :description="$t('purchases.no_shortages_found_desc')"
      icon="✨"
    />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  suggestions: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  isAllSelected: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

defineEmits(['toggle-select-all', 'toggle-item']);

const getUrgencyBadge = (urgency) => {
  switch (urgency) {
    case 'critical':
      return 'bg-rose-500/10 border-rose-500/30 text-rose-500 dark:text-rose-400';
    case 'warning':
      return 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400';
    default:
      return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
  }
};

const getUrgencyText = (urgency) => {
  switch (urgency) {
    case 'critical':
      return t('purchases.urgency_critical_badge');
    case 'warning':
      return t('purchases.urgency_warning_badge');
    default:
      return t('purchases.urgency_safe_badge');
  }
};
</script>
