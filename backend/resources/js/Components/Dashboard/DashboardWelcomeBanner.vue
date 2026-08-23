<template>
  <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
    <div>
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-theme-primary/10 text-theme-primary font-black text-sm">☕</span>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
          {{ $t('dashboard.welcome') }} {{ companyName }}
        </h1>
      </div>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-bold mt-1">
        {{ $t('dashboard.overview_subtitle') }}
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
      <router-link to="/pos" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-black text-xs rounded-2xl shadow-lg shadow-emerald-500/20 flex items-center gap-2 transition-all active:scale-95 cursor-pointer">
        <Plus class="w-4 h-4" />
        <span>{{ $t('dashboard.pos_fast') }}</span>
      </router-link>
      <router-link to="/purchases" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-2xl shadow-xs flex items-center gap-2 transition-all active:scale-95 cursor-pointer">
        <ShoppingCart class="w-4 h-4 text-slate-500 dark:text-slate-400" />
        <span>{{ $t('dashboard.new_purchase') }}</span>
      </router-link>
      <button type="button" @click="$emit('refresh')" :disabled="loading" class="p-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-2xl shadow-xs transition-all active:scale-95 cursor-pointer" :title="$t('dashboard.refresh_live')">
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
      </button>
    </div>
  </div>
</template>
<script setup>
import { Plus, ShoppingCart, RefreshCw } from 'lucide-vue-next';
defineProps({
  companyName: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});
defineEmits(['refresh']);
</script>