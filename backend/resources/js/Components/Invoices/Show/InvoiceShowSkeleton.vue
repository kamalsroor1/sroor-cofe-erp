<template>
  <div class="space-y-6 animate-pulse font-tajawal">
    <!-- Header Skeleton -->
    <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 space-y-4">
      <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
        <div class="h-4 w-32 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
        <div class="flex gap-2">
          <div class="h-6 w-20 bg-slate-200 dark:bg-slate-800 rounded-full"></div>
          <div class="h-6 w-20 bg-slate-200 dark:bg-slate-800 rounded-full"></div>
        </div>
      </div>
      <div class="flex justify-between items-center gap-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-slate-200 dark:bg-slate-800 rounded-2xl"></div>
          <div class="space-y-2">
            <div class="h-6 w-48 bg-slate-200 dark:bg-slate-800 rounded-xl"></div>
            <div class="h-3 w-64 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
          </div>
        </div>
        <div class="h-10 w-64 bg-slate-200 dark:bg-slate-800 rounded-2xl"></div>
      </div>
    </div>

    <!-- Actions Bar Skeleton -->
    <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 flex justify-between gap-3">
      <div class="flex gap-2">
        <div class="h-10 w-36 bg-slate-200 dark:bg-slate-800 rounded-2xl"></div>
        <div class="h-10 w-36 bg-slate-200 dark:bg-slate-800 rounded-2xl"></div>
        <div class="h-10 w-24 bg-slate-200 dark:bg-slate-800 rounded-2xl"></div>
      </div>
      <div class="h-10 w-24 bg-slate-200 dark:bg-slate-800 rounded-2xl"></div>
    </div>

    <!-- KPI Grid Skeleton -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div v-for="i in 6" :key="i" class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 space-y-3">
        <div class="h-3 w-16 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
        <div class="h-6 w-24 bg-slate-200 dark:bg-slate-800 rounded-xl"></div>
      </div>
    </div>

    <!-- Customer & Store Skeleton -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 space-y-3">
        <div class="h-4 w-28 bg-slate-200 dark:bg-slate-800 rounded-lg pb-3 border-b border-slate-100 dark:border-slate-800"></div>
        <div class="space-y-2 pt-2">
          <div class="h-4 w-full bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
          <div class="h-4 w-3/4 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
          <div class="h-4 w-1/2 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 space-y-3">
        <div class="h-4 w-28 bg-slate-200 dark:bg-slate-800 rounded-lg pb-3 border-b border-slate-100 dark:border-slate-800"></div>
        <div class="space-y-2 pt-2">
          <div class="h-4 w-full bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
          <div class="h-4 w-3/4 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
          <div class="h-4 w-1/2 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
        </div>
      </div>
    </div>

    <!-- Table Skeleton -->
    <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 space-y-4">
      <div class="h-4 w-32 bg-slate-200 dark:bg-slate-800 rounded-lg"></div>
      <div class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-10 w-full bg-slate-100 dark:bg-slate-800/60 rounded-xl"></div>
      </div>
    </div>
  </div>
</template>
