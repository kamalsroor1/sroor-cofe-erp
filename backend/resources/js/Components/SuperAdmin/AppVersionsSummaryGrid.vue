<template>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 font-tajawal">
    <div class="p-5 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg flex items-center justify-between">
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400 block font-bold">{{ $t('super.current_active_version') }}</span>
        <span class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block font-mono">
          v{{ summary.active_version || '1.0.0' }}
        </span>
      </div>
      <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
        <CheckCircle class="w-5 h-5" />
      </div>
    </div>

    <div class="p-5 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg flex items-center justify-between">
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400 block font-bold">{{ $t('super.total_inapp_downloads') }}</span>
        <span class="text-xl font-black text-theme-primary mt-1 block font-mono">
          {{ summary.total_downloads || 0 }} {{ $t('super.download_unit') }}
        </span>
      </div>
      <div class="w-11 h-11 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center">
        <Download class="w-5 h-5" />
      </div>
    </div>

    <div class="p-5 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg flex items-center justify-between">
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400 block font-bold">{{ $t('super.published_releases_count') }}</span>
        <span class="text-xl font-black text-purple-600 dark:text-purple-400 mt-1 block font-mono">
          {{ summary.total_releases || 0 }}
        </span>
      </div>
      <div class="w-11 h-11 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center">
        <Layers class="w-5 h-5" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { CheckCircle, Download, Layers } from 'lucide-vue-next';

defineProps({
  summary: { type: Object, default: () => ({}) },
});
</script>
