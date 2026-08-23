<template>
  <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <StatCardSkeleton v-for="i in 4" :key="i" />
  </div>

  <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Total In (الوارد) -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('inventory.total_in') }} {{ $t('inventory.total_in_sub') }}</div>
      <div class="text-xl font-black text-emerald-500 font-mono">
        +{{ formatQty(stats.total_in || 0) }} <span class="text-xs font-normal text-slate-400">{{ unit }}</span>
      </div>
    </div>

    <!-- Total Out (المنصرف) -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('inventory.total_out') }} {{ $t('inventory.total_out_sub') }}</div>
      <div class="text-xl font-black text-rose-500 font-mono">
        -{{ formatQty(stats.total_out || 0) }} <span class="text-xs font-normal text-slate-400">{{ unit }}</span>
      </div>
    </div>

    <!-- Net Movement (صافي الحركة) -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('inventory.net_movement') }}</div>
      <div
        class="text-xl font-black font-mono"
        :class="stats.net_movement >= 0 ? 'text-cyan-500 dark:text-cyan-400' : 'text-theme-primary'"
      >
        {{ stats.net_movement > 0 ? '+' : '' }}{{ formatQty(stats.net_movement || 0) }} <span class="text-xs font-normal text-slate-400">{{ unit }}</span>
      </div>
    </div>

    <!-- Current Stock (الرصيد الفعلي) -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('inventory.current_stock') }}</div>
      <div class="text-xl font-black text-slate-900 dark:text-white font-mono">
        {{ formatQty(stats.current_scope_stock || currentStock || 0) }} <span class="text-xs font-normal text-slate-400">{{ unit }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatQty } = useFormatters();

defineProps({
  stats: { type: Object, default: () => ({}) },
  unit: { type: String, default: '' },
  currentStock: { type: [Number, String], default: 0 },
  loading: { type: Boolean, default: false },
});
</script>
