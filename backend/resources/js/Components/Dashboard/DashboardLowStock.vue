<template>
  <DashboardSectionCard :title="$t('dashboard.low_stock')" dot-color="bg-rose-500" :dot-pulse="true" class="flex flex-col justify-between">
    <template #action>
      <router-link to="/smart-reorder" class="text-xs font-bold text-theme-primary hover:text-theme-primary transition flex items-center gap-1 cursor-pointer">
        <span>{{ $t('dashboard.smart_reorder_link') }}</span><span>←</span>
      </router-link>
    </template>
    <div class="space-y-2 max-h-[460px] overflow-y-auto pr-1">
      <div v-for="item in items" :key="item.id" class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-rose-500/30 transition flex items-center justify-between gap-3 group">
        <div class="min-w-0 flex-1">
          <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-theme-primary transition">{{ item.name }}</div>
          <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $t('dashboard.code') }}: {{ item.code || 'ITM-' + item.id }}</div>
        </div>
        <div class="text-end shrink-0">
          <div class="text-xs font-black text-rose-500 font-mono">{{ formatQty(item.current_stock) }} {{ item.unit }}</div>
          <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $t('dashboard.min_limit') }}: {{ formatQty(item.min_stock || 5) }}</div>
        </div>
      </div>
      <div v-if="items.length === 0" class="py-12 text-center text-xs text-slate-400 font-bold">
        {{ $t('dashboard.all_stock_safe') }}
      </div>
    </div>
  </DashboardSectionCard>
</template>
<script setup>
import DashboardSectionCard from '../Common/DashboardSectionCard.vue';
import { useFormatters } from '../../Composables/useFormatters';
const { formatQty } = useFormatters();
defineProps({ items: { type: Array, default: () => [] } });
</script>