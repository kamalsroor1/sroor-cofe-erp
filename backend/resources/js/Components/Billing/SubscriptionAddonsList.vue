<script setup>
import { Puzzle } from 'lucide-vue-next';
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
  addons: { type: Array, default: () => [] },
});

const { formatDate, formatNumber } = useFormatters();
</script>

<template>
  <section
    v-if="addons.length"
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal"
  >
    <header class="flex items-center gap-2.5">
      <Puzzle class="w-5 h-5 text-theme-primary" />
      <h2 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
        {{ $t('subscription.billing.addons.title') }}
      </h2>
    </header>
    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <li
        v-for="addon in addons"
        :key="addon.key"
        class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 flex items-center justify-between gap-2"
      >
        <div class="min-w-0">
          <p class="text-sm font-black text-slate-900 dark:text-white truncate">{{ addon.name }}</p>
          <p v-if="addon.ends_at" class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
            {{ $t('subscription.billing.addons.ends_at', { date: formatDate(addon.ends_at) }) }}
          </p>
        </div>
        <span class="text-xs font-black text-slate-600 dark:text-slate-300 whitespace-nowrap">
          {{ $t('subscription.billing.addons.quantity', { count: formatNumber(addon.quantity) }) }}
        </span>
      </li>
    </ul>
  </section>
</template>
