<template>
  <div class="space-y-6 max-w-6xl mx-auto font-tajawal animate-pulse">
    <!-- Header Skeleton -->
    <div
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4"
    >
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
        <div class="space-y-2">
          <div class="w-36 h-5 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
          <div class="w-56 h-3 rounded-lg bg-slate-100 dark:bg-slate-800/60"></div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="w-48 sm:w-64 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800"></div>
        <div class="w-24 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800"></div>
        <div class="w-28 h-11 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
      </div>
    </div>

    <!-- Mobile Hub Skeleton -->
    <div v-if="isMobile && !hasSelectedTab" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
      <div
        v-for="i in 5"
        :key="i"
        class="p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-start gap-4"
      >
        <div class="w-12 h-12 rounded-2xl bg-slate-200 dark:bg-slate-800 shrink-0"></div>
        <div class="flex-1 space-y-2.5">
          <div class="w-28 h-4 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
          <div class="w-full h-3 rounded-lg bg-slate-100 dark:bg-slate-800/60"></div>
          <div class="w-3/4 h-3 rounded-lg bg-slate-100 dark:bg-slate-800/60"></div>
        </div>
      </div>
    </div>

    <!-- Desktop Split Skeleton -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Sidebar Navigation Shimmer (4 cols) -->
      <div class="hidden lg:block lg:col-span-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 space-y-2">
          <div
            v-for="i in 5"
            :key="i"
            class="p-3.5 rounded-2xl flex items-center gap-3.5"
            :class="i === 1 ? 'bg-slate-100 dark:bg-slate-800/60' : ''"
          >
            <div class="w-10 h-10 rounded-2xl bg-slate-200 dark:bg-slate-800 shrink-0"></div>
            <div class="flex-1 space-y-1.5">
              <div class="w-24 h-4 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
              <div class="w-36 h-2.5 rounded-lg bg-slate-100 dark:bg-slate-800/60"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Form Section Shimmer (8 cols) -->
      <div class="lg:col-span-8">
        <div
          class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-7 space-y-6"
        >
          <!-- Section Card Header -->
          <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
              <div class="space-y-1.5">
                <div class="w-32 h-4 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
                <div class="w-48 h-3 rounded-lg bg-slate-100 dark:bg-slate-800/60"></div>
              </div>
            </div>
            <div class="w-20 h-9 rounded-xl bg-slate-200 dark:bg-slate-800"></div>
          </div>

          <!-- Form Fields Rows -->
          <div class="space-y-4">
            <div v-for="i in 4" :key="i" class="space-y-2">
              <div class="w-24 h-3 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
              <div class="w-full h-11 rounded-2xl bg-slate-100 dark:bg-slate-800/50"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  isMobile: {
    type: Boolean,
    default: false,
  },
  hasSelectedTab: {
    type: Boolean,
    default: false,
  },
});
</script>
