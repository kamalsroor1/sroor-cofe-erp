<template>
  <div class="space-y-4 max-w-7xl mx-auto font-tajawal">
    <router-link
      to="/"
      data-testid="all-screens-back"
      class="min-h-[44px] inline-flex items-center gap-2 text-xs sm:text-sm font-black text-slate-600 dark:text-slate-300 hover:text-theme-primary transition cursor-pointer"
    >
      <ArrowRight class="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
      <span>{{ $t('nav.dashboard') }}</span>
    </router-link>
    <DashboardAppMenuHub />
  </div>
</template>

<script setup>
import { ArrowRight } from 'lucide-vue-next';
import DashboardAppMenuHub from '../Components/Dashboard/DashboardAppMenuHub.vue';
</script>
