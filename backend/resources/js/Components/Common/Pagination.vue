<script setup>
import { computed, useAttrs } from 'vue';

const props = defineProps({
  links: {
    type: Array,
    default: () => [],
  },
  from: {
    type: [Number, String],
    default: null,
  },
  to: {
    type: [Number, String],
    default: null,
  },
  total: {
    type: [Number, String],
    default: null,
  },
  currentPage: {
    type: [Number, String],
    default: null,
  },
  lastPage: {
    type: [Number, String],
    default: null,
  },
  perPage: {
    type: [Number, String],
    default: null,
  },
  pagination: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['page-change']);
const attrs = useAttrs();

const activeLinks = computed(() => {
  if (props.links && props.links.length > 0) {
    return props.links;
  }
  if (props.pagination?.links && props.pagination.links.length > 0) {
    return props.pagination.links;
  }
  return [];
});

const hasFullLinks = computed(() => {
  return activeLinks.value && activeLinks.value.length > 3;
});

const currentPage = computed(() => {
  const val =
    props.currentPage ||
    props.pagination?.current_page ||
    props.pagination?.currentPage ||
    attrs['current-page'] ||
    attrs.currentPage ||
    1;
  return Number(val) || 1;
});

const lastPage = computed(() => {
  const val =
    props.lastPage ||
    props.pagination?.last_page ||
    props.pagination?.lastPage ||
    attrs['last-page'] ||
    attrs.lastPage ||
    1;
  return Number(val) || 1;
});

const perPage = computed(() => {
  const val =
    props.perPage ||
    props.pagination?.per_page ||
    props.pagination?.perPage ||
    attrs['per-page'] ||
    attrs.perPage ||
    15;
  return Number(val) || 15;
});

const total = computed(() => {
  const val = props.total || props.pagination?.total || 0;
  return Number(val) || 0;
});

const from = computed(() => {
  const val =
    props.from || props.pagination?.from || (total.value > 0 ? (currentPage.value - 1) * perPage.value + 1 : 0);
  return Number(val) || 0;
});

const to = computed(() => {
  const val = props.to || props.pagination?.to || Math.min(currentPage.value * perPage.value, total.value);
  return Number(val) || 0;
});

const extractPage = (url) => {
  if (!url) return null;
  try {
    const parsed = new URL(url, window.location.origin);
    return parsed.searchParams.get('page');
  } catch {
    const match = url.match(/[?&]page=(\d+)/);
    return match ? match[1] : null;
  }
};

const handlePageClick = (link) => {
  if (!link.url) return;
  const page = extractPage(link.url);
  if (page) {
    emit('page-change', parseInt(page, 10));
  }
};
</script>

<template>
  <div
    v-if="hasFullLinks || lastPage > 1"
    class="pt-4 border-t border-slate-200 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 font-sans select-none"
  >
    <span class="text-xs text-slate-500 dark:text-slate-400 font-tajawal font-bold">
      {{ $t('common.showing_results', { from: from || 0, to: to || 0, total: total || 0 }) }}
    </span>

    <!-- Existing links layout when links > 3 -->
    <div v-if="hasFullLinks" class="flex items-center gap-1 flex-wrap justify-center font-tajawal">
      <template v-for="(link, lIdx) in activeLinks" :key="lIdx">
        <button
          v-if="link.url"
          type="button"
          @click="handlePageClick(link)"
          class="h-9 min-w-[36px] coarse:min-h-[44px] coarse:min-w-[44px] px-3 rounded-xl text-xs font-bold transition flex items-center justify-center cursor-pointer active:scale-95 shadow-xs"
          :class="[
            link.active
              ? 'bg-theme-primary text-white font-black shadow-theme-sm'
              : 'bg-white dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700',
          ]"
          v-html="link.label"
        />
        <span
          v-else
          class="h-9 min-w-[36px] coarse:min-h-[44px] coarse:min-w-[44px] px-3 rounded-xl text-xs text-slate-400 dark:text-slate-600 font-bold flex items-center justify-center opacity-60"
          v-html="link.label"
        />
      </template>
    </div>

    <!-- Fallback prev/next navigation when links missing or <= 3 and lastPage > 1 -->
    <div v-else-if="lastPage > 1" class="flex items-center gap-2 flex-wrap justify-center font-tajawal">
      <button
        type="button"
        :disabled="currentPage <= 1"
        @click="$emit('page-change', currentPage - 1)"
        class="min-h-[44px] min-w-[44px] px-3.5 rounded-xl text-xs font-bold transition flex items-center justify-center bg-white dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer active:scale-95 shadow-xs"
      >
        {{ $t('common.previous') }}
      </button>

      <span class="text-slate-600 dark:text-slate-400 font-bold text-xs px-2 select-none">
        {{ $t('pagination.page_of', { current: currentPage, total: lastPage }) }}
      </span>

      <button
        type="button"
        :disabled="currentPage >= lastPage"
        @click="$emit('page-change', currentPage + 1)"
        class="min-h-[44px] min-w-[44px] px-3.5 rounded-xl text-xs font-bold transition flex items-center justify-center bg-white dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer active:scale-95 shadow-xs"
      >
        {{ $t('common.next') }}
      </button>
    </div>
  </div>
</template>
