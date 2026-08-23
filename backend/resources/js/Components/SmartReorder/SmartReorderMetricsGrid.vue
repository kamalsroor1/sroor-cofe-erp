<template>
  <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <StatCardSkeleton v-for="i in 4" :key="i" />
  </div>

  <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Critical Items -->
    <div class="p-4 rounded-2xl bg-rose-50/60 dark:bg-slate-900/90 border border-rose-200 dark:border-rose-500/30 shadow-sm dark:shadow-md space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('purchases.critical_shortage_range') }}</span>
        <AlertTriangle class="w-4 h-4 text-rose-500" />
      </div>
      <div class="text-2xl font-black text-rose-500 font-mono">
        {{ metrics.critical_count || 0 }} <span class="text-xs text-slate-400">{{ $t('inventory.item_unit') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.critical_shortage_desc') }}</span>
    </div>

    <!-- Warning Items -->
    <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-slate-900/90 border border-amber-200 dark:border-amber-500/30 shadow-sm dark:shadow-md space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('purchases.warning_supply_range') }}</span>
        <Clock class="w-4 h-4 text-amber-500" />
      </div>
      <div class="text-2xl font-black text-amber-500 font-mono">
        {{ metrics.warning_count || 0 }} <span class="text-xs text-slate-400">{{ $t('inventory.item_unit') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.warning_supply_desc') }}</span>
    </div>

    <!-- Safe Items -->
    <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-slate-900/90 border border-emerald-200 dark:border-emerald-500/30 shadow-sm dark:shadow-md space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('purchases.safe_stock_range') }}</span>
        <ShieldCheck class="w-4 h-4 text-emerald-500" />
      </div>
      <div class="text-2xl font-black text-emerald-500 font-mono">
        {{ metrics.safe_count || 0 }} <span class="text-xs text-slate-400">{{ $t('inventory.item_unit') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.safe_stock_desc') }}</span>
    </div>

    <!-- Estimated Total Cost -->
    <div class="p-4 rounded-2xl bg-purple-50/60 dark:bg-slate-900/90 border border-purple-200 dark:border-purple-500/30 shadow-sm dark:shadow-md space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('purchases.estimated_reorder_cost') }}</span>
        <Sparkles class="w-4 h-4 text-purple-500" />
      </div>
      <div class="text-xl font-black text-purple-600 dark:text-purple-400 font-mono">
        {{ formatMoney(metrics.total_estimated_cost || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.estimated_reorder_sub') }}</span>
    </div>
  </div>
</template>

<script setup>
import { AlertTriangle, Clock, ShieldCheck, Sparkles } from 'lucide-vue-next';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  metrics: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});
</script>
