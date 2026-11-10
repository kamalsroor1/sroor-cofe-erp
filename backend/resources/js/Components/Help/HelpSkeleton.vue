<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-pulse font-tajawal" data-testid="help-skeleton">
    <div
      class="lg:col-span-4 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-4"
    >
      <div class="h-11 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
      <div class="space-y-2.5">
        <div v-for="i in 7" :key="i" class="h-12 rounded-2xl bg-slate-100 dark:bg-slate-800/70"></div>
      </div>
    </div>
    <div
      class="lg:col-span-8 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 sm:p-8 space-y-6"
    >
      <div class="space-y-2">
        <div class="h-5 w-28 rounded-full bg-slate-200 dark:bg-slate-800"></div>
        <div class="h-8 w-2/3 rounded-xl bg-slate-200 dark:bg-slate-800"></div>
      </div>
      <div v-for="s in 3" :key="s" class="space-y-3 pt-2">
        <div class="h-6 w-1/3 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
        <div class="h-4 w-full rounded-lg bg-slate-100 dark:bg-slate-800/60"></div>
        <div class="h-4 w-5/6 rounded-lg bg-slate-100 dark:bg-slate-800/60"></div>
      </div>
    </div>
  </div>
</template>
