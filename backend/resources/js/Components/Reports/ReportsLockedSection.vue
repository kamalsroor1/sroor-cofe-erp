<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl p-6 sm:p-10 font-tajawal"
    role="status"
    data-testid="report-locked"
  >
    <div class="max-w-md mx-auto text-center space-y-3">
      <div
        class="mx-auto w-14 h-14 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center"
      >
        <Lock class="w-7 h-7" aria-hidden="true" />
      </div>
      <h3 class="text-base font-black text-slate-900 dark:text-white">{{ $t('reports.locked_title') }}</h3>
      <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{{ $t('reports.locked_desc') }}</p>
    </div>
  </div>
</template>

<script setup>
import { Lock } from 'lucide-vue-next';
</script>
