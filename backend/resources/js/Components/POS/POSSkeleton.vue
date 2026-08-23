<template>
  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 flex flex-col font-tajawal select-none p-2 sm:p-4 space-y-3 sm:space-y-4" dir="rtl">
    <!-- 🔝 1. POS Header Skeleton -->
    <div class="p-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xs">
      <div class="flex items-center gap-3 w-full sm:w-auto">
        <Skeleton width="w-10" height="h-10" rounded="rounded-2xl" />
        <div class="space-y-1.5">
          <Skeleton width="w-32" height="h-4" rounded="rounded-md" />
          <Skeleton width="w-24" height="h-3" rounded="rounded-md" />
        </div>
      </div>
      <!-- Big Search Bar Placeholder -->
      <div class="flex-1 max-w-xl w-full">
        <Skeleton width="w-full" height="h-12" rounded="rounded-2xl" />
      </div>
      <div class="flex items-center gap-2">
        <Skeleton width="w-24" height="h-10" rounded="rounded-2xl" />
        <Skeleton width="w-10" height="h-10" rounded="rounded-2xl" />
      </div>
    </div>

    <!-- 🖥️ 2. Main Workspace Skeleton (Cart + Pinned Items + Checkout) -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4 overflow-hidden">
      <!-- Right: Cart & Pinned Items Skeleton -->
      <div class="lg:col-span-7 xl:col-span-8 flex flex-col justify-between p-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 space-y-4 shadow-xs">
        <div class="space-y-3">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <Skeleton width="w-36" height="h-4" rounded="rounded-md" />
            <Skeleton width="w-20" height="h-6" rounded="rounded-xl" />
          </div>
          <!-- Cart item rows -->
          <div v-for="n in 3" :key="n" class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
            <div class="space-y-1.5">
              <Skeleton width="w-40" height="h-4" rounded="rounded-md" />
              <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
            </div>
            <div class="flex items-center gap-2">
              <Skeleton width="w-24" height="h-8" rounded="rounded-xl" />
              <Skeleton width="w-16" height="h-6" rounded="rounded-md" />
            </div>
          </div>
        </div>

        <!-- Pinned Quick Grid Skeleton -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
          <Skeleton width="w-28" height="h-3.5" rounded="rounded-md" />
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <Skeleton v-for="n in 4" :key="n" width="w-full" height="h-16" rounded="rounded-2xl" />
          </div>
        </div>
      </div>

      <!-- Left: Checkout Panel Skeleton -->
      <div class="lg:col-span-5 xl:col-span-4 p-5 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 space-y-5 shadow-xs flex flex-col justify-between">
        <div class="space-y-4">
          <Skeleton width="w-32" height="h-5" rounded="rounded-md" />
          <!-- Breakdown lines -->
          <div class="space-y-2 pt-2">
            <div v-for="n in 3" :key="n" class="flex items-center justify-between">
              <Skeleton width="w-24" height="h-3.5" rounded="rounded-md" />
              <Skeleton width="w-16" height="h-3.5" rounded="rounded-md" />
            </div>
          </div>
          <!-- Big Net Total Skeleton -->
          <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-100 dark:border-slate-800 space-y-2 text-center">
            <Skeleton width="w-20" height="h-3" rounded="rounded-md" className="mx-auto" />
            <Skeleton width="w-36" height="h-8" rounded="rounded-xl" className="mx-auto" />
          </div>
          <!-- Payment types grid -->
          <div class="grid grid-cols-3 gap-2 pt-1">
            <Skeleton v-for="n in 3" :key="n" width="w-full" height="h-12" rounded="rounded-2xl" />
          </div>
        </div>

        <!-- Giant Checkout Button Skeleton -->
        <Skeleton width="w-full" height="h-14" rounded="rounded-2xl" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';
</script>
