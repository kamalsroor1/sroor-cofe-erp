<template>
  <div class="h-full max-h-full min-h-0 overflow-hidden bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-tajawal select-none" dir="rtl">
    
    <!-- 🔝 1. POS Header Skeleton -->
    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-2.5 shrink-0 shadow-xs z-30">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Brand & Shift -->
        <div class="flex items-center gap-3 shrink-0">
          <Skeleton width="w-10" height="h-10" rounded="rounded-xl" />
          <div class="flex items-center gap-2">
            <Skeleton width="w-10" height="h-10" rounded="rounded-xl" />
            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <Skeleton width="w-28" height="h-4" rounded="rounded-md" />
                <Skeleton width="w-12" height="h-3" rounded="rounded-md" />
              </div>
              <Skeleton width="w-36" height="h-3" rounded="rounded-md" />
            </div>
          </div>
        </div>

        <!-- Big Search Command Bar -->
        <div class="flex-1 max-w-3xl">
          <Skeleton width="w-full" height="h-12" rounded="rounded-2xl" />
        </div>

        <!-- Header Action Tools -->
        <div class="flex items-center gap-2 shrink-0">
          <Skeleton width="w-24" height="h-11" rounded="rounded-xl" />
          <Skeleton width="w-36" height="h-11" rounded="rounded-xl" />
          <Skeleton width="w-24" height="h-10" rounded="rounded-xl" />
          <Skeleton width="w-11" height="h-11" rounded="rounded-xl" />
        </div>
      </div>
    </header>

    <!-- 🖥️ 2. Main Workspace: Hybrid Layout Skeleton -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
      
      <!-- 🛒 Invoice Cart & Payment Checkout Panel (DOMINANT HERO - flex-1) -->
      <section class="flex-1 flex flex-col justify-between p-3.5 bg-slate-50 dark:bg-slate-950 border-e border-slate-200 dark:border-slate-800 overflow-hidden order-2 lg:order-1 min-w-0">
        
        <!-- Top Section: Cart Items Table -->
        <div class="flex-1 overflow-hidden flex flex-col min-h-[220px]">
          <!-- Cart Header -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
            <div class="flex items-center gap-2">
              <Skeleton width="w-6" height="h-6" rounded="rounded-md" />
              <Skeleton width="w-28" height="h-4" rounded="rounded-md" />
              <Skeleton width="w-16" height="h-5" rounded="rounded-full" />
            </div>
            <Skeleton width="w-24" height="h-3" rounded="rounded-md" className="hidden sm:block" />
          </div>

          <!-- Table Container -->
          <div class="flex-1 overflow-hidden my-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-3 space-y-3">
            <div class="hidden md:flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800 text-xs">
              <Skeleton width="w-8" height="h-3" rounded="rounded-md" />
              <Skeleton width="w-40" height="h-3" rounded="rounded-md" />
              <Skeleton width="w-24" height="h-3" rounded="rounded-md" />
              <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
              <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
              <Skeleton width="w-8" height="h-3" rounded="rounded-md" />
            </div>

            <!-- Cart Table Items -->
            <div v-for="n in 4" :key="n" class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/60 flex items-center justify-between gap-3">
              <Skeleton width="w-6" height="h-6" rounded="rounded-md" />
              <div class="space-y-1.5 flex-1 min-w-0">
                <Skeleton width="w-44" height="h-4" rounded="rounded-md" />
                <Skeleton width="w-24" height="h-3" rounded="rounded-md" />
              </div>
              <div class="flex items-center gap-1">
                <Skeleton width="w-8" height="h-8" rounded="rounded-lg" />
                <Skeleton width="w-14" height="h-8" rounded="rounded-lg" />
                <Skeleton width="w-8" height="h-8" rounded="rounded-lg" />
              </div>
              <Skeleton width="w-20" height="h-8" rounded="rounded-lg" />
              <Skeleton width="w-20" height="h-5" rounded="rounded-md" />
              <Skeleton width="w-8" height="h-8" rounded="rounded-lg" />
            </div>
          </div>
        </div>

        <!-- Bottom Section: Checkout & Financial Panel -->
        <div class="shrink-0 pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2.5">
          <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
            <div class="flex items-center justify-between">
              <Skeleton width="w-28" height="h-3.5" rounded="rounded-md" />
              <Skeleton width="w-20" height="h-3.5" rounded="rounded-md" />
            </div>
            <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800">
              <Skeleton width="w-24" height="h-3" rounded="rounded-md" />
              <div class="flex items-center gap-1">
                <Skeleton v-for="d in 5" :key="d" width="w-8" height="h-5" rounded="rounded-md" />
              </div>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
              <Skeleton width="w-24" height="h-5" rounded="rounded-md" />
              <Skeleton width="w-32" height="h-7" rounded="rounded-lg" />
            </div>
          </div>

          <div class="grid grid-cols-3 gap-1.5">
            <Skeleton width="w-full" height="h-10" rounded="rounded-xl" />
            <Skeleton width="w-full" height="h-10" rounded="rounded-xl" />
            <Skeleton width="w-full" height="h-10" rounded="rounded-xl" />
          </div>

          <Skeleton width="w-full" height="h-12" rounded="rounded-2xl" />
        </div>
      </section>

      <!-- 🍕 Side: Visual Product Grid Skeleton (3 Columns) -->
      <main class="w-full lg:w-[350px] xl:w-[400px] 2xl:w-[440px] flex flex-col overflow-hidden shrink-0 bg-slate-100/60 dark:bg-slate-900/60 border-e border-slate-200 dark:border-slate-800 order-1 lg:order-2">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 shrink-0 bg-white dark:bg-slate-900">
          <div class="flex items-center gap-2">
            <Skeleton width="w-4" height="h-4" rounded="rounded-md" />
            <Skeleton width="w-24" height="h-4" rounded="rounded-md" />
            <Skeleton width="w-8" height="h-4" rounded="rounded-md" />
          </div>
          <Skeleton width="w-16" height="h-4" rounded="rounded-md" />
        </div>
        
        <div class="p-3.5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-3 gap-3.5 flex-1 overflow-hidden">
          <div v-for="n in 9" :key="n" class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900 flex flex-col justify-between min-h-[96px] space-y-2">
            <div class="flex items-center justify-between">
              <Skeleton width="w-12" height="h-3" rounded="rounded-md" />
              <Skeleton width="w-10" height="h-3" rounded="rounded-md" />
            </div>
            <Skeleton width="w-full" height="h-4" rounded="rounded-md" />
            <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800/60">
              <Skeleton width="w-14" height="h-4" rounded="rounded-md" />
              <Skeleton width="w-5" height="h-5" rounded="rounded-md" />
            </div>
          </div>
        </div>
      </main>

      <!-- 📂 Right (in RTL): Compact Vertical Category Sidebar Skeleton -->
      <aside class="w-14 sm:w-36 md:w-40 shrink-0 flex flex-col bg-white dark:bg-slate-900/95 border-s border-slate-200 dark:border-slate-800 overflow-hidden order-3">
        <div class="p-2.5 border-b border-slate-200 dark:border-slate-800">
          <Skeleton width="w-16" height="h-3" rounded="rounded-md" className="hidden sm:block" />
        </div>
        <div class="p-1.5 space-y-1.5 flex-1 overflow-hidden">
          <Skeleton v-for="n in 7" :key="n" width="w-full" height="h-10" rounded="rounded-xl" />
        </div>
      </aside>

    </div>
  </div>
</template>

<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';
</script>
