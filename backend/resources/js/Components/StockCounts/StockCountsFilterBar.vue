<script setup>
import { Search } from 'lucide-vue-next';
import { useTrans } from '@/Composables/useTrans';

const status = defineModel('status', { type: String, default: 'all' });
const search = defineModel('search', { type: String, default: '' });

const { t } = useTrans();
const OPTIONS = ['all', 'open', 'posted', 'cancelled'];
const label = (key) => {
  if (key === 'all') return t('stock_counts.filter_all');
  if (key === 'open') return t('stock_counts.filter_open');
  return t(`stock_counts.status.${key}`);
};
</script>

<template>
  <div
    class="flex flex-col md:flex-row md:items-center gap-3 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-3 shadow-sm font-tajawal"
  >
    <label class="relative flex-1">
      <span class="sr-only">{{ t('stock_counts.search_placeholder') }}</span>
      <Search class="w-5 h-5 absolute top-1/2 -translate-y-1/2 start-3 text-slate-400 pointer-events-none" />
      <input
        v-model="search"
        type="search"
        autocomplete="off"
        class="w-full min-h-[44px] ps-11 pe-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
        :placeholder="t('stock_counts.search_placeholder')"
      />
    </label>
    <div class="flex flex-wrap gap-2" role="tablist">
      <button
        v-for="key in OPTIONS"
        :key="key"
        type="button"
        role="tab"
        :aria-selected="status === key"
        class="min-h-[44px] px-3 rounded-xl text-xs font-bold border transition cursor-pointer"
        :class="
          status === key
            ? 'bg-[var(--color-primary)] text-white border-transparent'
            : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
        "
        @click="status = key"
      >
        {{ label(key) }}
      </button>
    </div>
  </div>
</template>
