<script setup>
import { ref } from 'vue';
import { ScanBarcode, Search } from 'lucide-vue-next';
import { useTrans } from '@/Composables/useTrans';

defineProps({
  canScan: { type: Boolean, default: true },
});

const emit = defineEmits(['scan']);
const search = defineModel('search', { type: String, default: '' });
const filter = defineModel('filter', { type: String, default: 'all' });

const { t } = useTrans();
const code = ref('');

const FILTERS = ['all', 'uncounted', 'counted', 'differences'];
const filterLabel = (key) => (key === 'all' ? t('stock_counts.filter_all') : t(`stock_counts.filter_${key}`));

const submitScan = () => {
  const value = code.value.trim();
  if (!value) return;
  emit('scan', value);
  code.value = '';
};
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm space-y-3 font-tajawal"
  >
    <div class="grid gap-3 md:grid-cols-2">
      <label v-if="canScan" class="relative block">
        <span class="sr-only">{{ t('stock_counts.scan_placeholder') }}</span>
        <ScanBarcode
          class="w-5 h-5 absolute top-1/2 -translate-y-1/2 start-3 text-[var(--color-primary)] pointer-events-none"
        />
        <input
          v-model="code"
          type="text"
          autocomplete="off"
          inputmode="text"
          enterkeyhint="done"
          data-testid="stock-count-scan"
          class="w-full min-h-[48px] ps-11 pe-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
          :placeholder="t('stock_counts.scan_placeholder')"
          @keydown.enter.prevent="submitScan"
        />
      </label>
      <label class="relative block">
        <span class="sr-only">{{ t('stock_counts.search_items') }}</span>
        <Search class="w-5 h-5 absolute top-1/2 -translate-y-1/2 start-3 text-slate-400 pointer-events-none" />
        <input
          v-model="search"
          type="search"
          autocomplete="off"
          data-testid="stock-count-search"
          class="w-full min-h-[48px] ps-11 pe-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
          :placeholder="t('stock_counts.search_items')"
        />
      </label>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex flex-wrap gap-2" role="tablist">
        <button
          v-for="key in FILTERS"
          :key="key"
          type="button"
          role="tab"
          :aria-selected="filter === key"
          class="min-h-[40px] px-3 rounded-xl text-xs font-bold border transition cursor-pointer"
          :class="
            filter === key
              ? 'bg-[var(--color-primary)] text-white border-transparent'
              : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
          "
          @click="filter = key"
        >
          {{ filterLabel(key) }}
        </button>
      </div>
      <p v-if="canScan" class="text-[11px] text-slate-400">{{ t('stock_counts.scan_hint') }}</p>
    </div>
  </section>
</template>
