<script setup>
import { ChevronRight, ChevronLeft } from 'lucide-vue-next';

defineProps({
  page: { type: Number, required: true },
  lastPage: { type: Number, required: true },
});

defineEmits(['change']);
</script>

<template>
  <nav v-if="lastPage > 1" class="flex items-center justify-between gap-3 font-tajawal">
    <button
      type="button"
      :disabled="page <= 1"
      class="min-h-[44px] px-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-black flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      @click="$emit('change', page - 1)"
    >
      <ChevronRight class="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
      <span>{{ $t('billing.console.queue.previous') }}</span>
    </button>
    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
      {{ $t('billing.console.queue.page_of', { current: page, last: lastPage }) }}
    </span>
    <button
      type="button"
      :disabled="page >= lastPage"
      class="min-h-[44px] px-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-black flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      @click="$emit('change', page + 1)"
    >
      <span>{{ $t('billing.console.queue.next') }}</span>
      <ChevronLeft class="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
    </button>
  </nav>
</template>
