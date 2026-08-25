<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 font-tajawal">
    <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
        <span>{{ $t('super.users_limit_label') }}</span>
        <Users class="w-4 h-4 text-slate-400" />
      </div>
      <div class="text-2xl font-black text-slate-900 dark:text-white font-mono mt-3">
        {{ stats?.users_count || 0 }}
      </div>
      <div class="text-[10px] text-slate-400 mt-1 font-sans">{{ $t('super.active_users_stat') }}</div>
    </div>

    <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
        <span>{{ $t('super.stores_limit_label') }}</span>
        <Store class="w-4 h-4 text-cyan-500" />
      </div>
      <div class="text-2xl font-black text-cyan-600 dark:text-cyan-400 font-mono mt-3">
        {{ stats?.stores_count || 0 }}
      </div>
      <div class="text-[10px] text-slate-400 mt-1 font-sans">{{ $t('super.stores_stat') }}</div>
    </div>

    <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
        <span>{{ $t('super.items_limit_label') }}</span>
        <Package class="w-4 h-4 text-purple-500" />
      </div>
      <div class="text-2xl font-black text-purple-600 dark:text-purple-400 font-mono mt-3">
        {{ stats?.items_count || 0 }}
      </div>
      <div class="text-[10px] text-slate-400 mt-1 font-sans">{{ $t('super.items_stat') }}</div>
    </div>

    <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
      <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
        <span>{{ $t('super.invoices_limit_label') }}</span>
        <Receipt class="w-4 h-4 text-theme-primary" />
      </div>
      <div class="text-2xl font-black text-theme-primary font-mono mt-3">
        {{ stats?.invoices_count || 0 }}
      </div>
      <div class="text-[10px] text-slate-400 mt-1 font-sans">{{ $t('super.invoices_stat') }}</div>
    </div>

    <div class="bg-white dark:bg-slate-900/90 p-5 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between col-span-2 sm:col-span-1">
      <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-bold">
        <span>{{ $t('reports.total_sales') }}</span>
        <Banknote class="w-4 h-4 text-emerald-500" />
      </div>
      <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-3 truncate">
        {{ formatMoney(stats?.total_sales || 0) }} <span class="text-xs font-sans">ج.م</span>
      </div>
      <div class="text-[10px] text-slate-400 mt-1 font-sans">{{ $t('super.total_sales_stat') }}</div>
    </div>
  </div>
</template>

<script setup>
import { Users, Store, Package, Receipt, Banknote } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  stats: { type: Object, default: () => ({}) },
});
</script>
