<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5">
      <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
        <Layers class="w-4 h-4 text-theme-primary" />
        <span>{{ $t('super.tenants_distribution') }}</span>
      </h2>
    </div>

    <div v-if="planStats.length > 0" class="space-y-3">
      <div
        v-for="p in planStats"
        :key="p.id"
        class="p-3 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between text-xs"
      >
        <div>
          <div class="font-bold text-slate-900 dark:text-white">{{ p.name }}</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ $t('super.monthly_rate', { amount: formatMoney(p.price_monthly) }) }}</div>
        </div>
        <div class="text-end font-mono">
          <span class="px-2.5 py-1 bg-purple-500/10 border border-purple-500/30 text-purple-600 dark:text-purple-400 rounded-full font-bold">
            {{ $t('super.subscribers_count', { count: p.tenants_count }) }}
          </span>
        </div>
      </div>
    </div>

    <div v-else class="p-8 text-center text-xs text-slate-400 font-bold">
      {{ $t('super.no_tenants_registered') }}
    </div>
  </div>
</template>

<script setup>
import { Layers } from 'lucide-vue-next';

defineProps({
  planStats: { type: Array, default: () => [] },
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
