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

    <!-- 🖥️ 2. Main Workspace Skeleton (Cart + Product Grid + Category Sidebar) -->
    <div class="flex-1 flex flex-col lg:flex-row gap-3 sm:gap-4 overflow-hidden">
      <!-- 🛒 Cart & Checkout Skeleton -->
      <div class="w-full lg:w-[400px] xl:w-[450px] 2xl:w-[490px] flex flex-col justify-between p-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 space-y-4 shadow-xs shrink-0 order-2 lg:order-1">
        <div class="space-y-3 flex-1">
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
              <Skeleton width="w-20" height="h-7" rounded="rounded-xl" />
            </div>
          </div>
        </div>

        <!-- Checkout part -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-3">
          <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-100 dark:border-slate-800 text-center space-y-1">
            <Skeleton width="w-16" height="h-3" rounded="rounded-md" className="mx-auto" />
            <Skeleton width="w-28" height="h-6" rounded="rounded-xl" className="mx-auto" />
          </div>
          <Skeleton width="w-full" height="h-12" rounded="rounded-2xl" />
        </div>
      </div>

      <!-- 🍕 Product Grid Skeleton -->
      <div class="flex-1 p-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs order-1 lg:order-2 flex flex-col">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
          <Skeleton width="w-32" height="h-5" rounded="rounded-md" />
          <Skeleton width="w-16" height="h-5" rounded="rounded-md" />
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-2.5 flex-1">
          <Skeleton v-for="n in 15" :key="n" width="w-full" height="h-[105px]" rounded="rounded-2xl" />
        </div>
      </div>

      <!-- 📂 Category Sidebar Skeleton -->
      <div class="w-16 sm:w-44 shrink-0 p-3 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 space-y-2 shadow-xs order-3">
        <Skeleton width="w-20" height="h-4" rounded="rounded-md" className="hidden sm:block mb-3" />
        <Skeleton v-for="n in 6" :key="n" width="w-full" height="h-12" rounded="rounded-xl" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';
</script>
