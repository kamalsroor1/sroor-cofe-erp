<template>
  <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-950" dir="rtl">
    <!-- Grid Header: category name + count -->
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 shrink-0">
      <div class="flex items-center gap-2">
        <DynamicIcon :name="activeCategoryIcon" class="w-4 h-4 text-theme-primary shrink-0" />
        <h2 class="text-sm font-black text-slate-700 dark:text-slate-200">{{ activeCategoryName }}</h2>
        <span
          class="text-xs font-mono bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded-lg"
          data-testid="pos-grid-count"
          >{{ visibleCount }}</span
        >
      </div>
    </div>

    <!-- Products grid: per-branch cards per row, sized by the panel width (container query) -->
    <div ref="scrollRef" class="flex-1 overflow-y-auto custom-scrollbar p-3.5 @container">
      <div v-if="isLoading" class="grid gap-3" :class="gridClass" :style="gridStyle" aria-busy="true">
        <div
          v-for="n in 9"
          :key="n"
          class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/90 p-2.5 space-y-2 min-h-[112px]"
        >
          <Skeleton width="w-2/3" height="h-3" rounded="rounded-md" />
          <Skeleton width="w-full" height="h-4" rounded="rounded-md" />
          <div class="grid grid-cols-2 gap-1.5 pt-2">
            <Skeleton height="h-9" rounded="rounded-lg" />
            <Skeleton height="h-9" rounded="rounded-lg" />
          </div>
        </div>
      </div>

      <template v-else-if="items.length">
        <div class="grid gap-3" :class="gridClass" :style="gridStyle" data-testid="pos-product-grid">
          <POSProductButton
            v-for="item in items"
            :key="item.id"
            :item="item"
            :category-color="getCategoryColor(item)"
            :active-price-tier="activePriceTier"
            :show-image="showImages"
            @add-item="$emit('add-item', $event)"
          />
        </div>

        <div ref="sentinelRef" class="h-px" aria-hidden="true"></div>

        <div v-if="isLoadingMore" class="grid gap-3 mt-3" :class="gridClass" :style="gridStyle" aria-busy="true">
          <Skeleton v-for="n in 3" :key="n" height="h-28" rounded="rounded-xl" />
        </div>
        <div v-else-if="hasMore" class="flex justify-center mt-3">
          <button
            type="button"
            class="min-h-11 px-5 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
            data-testid="pos-load-more"
            @click="$emit('load-more')"
          >
            {{ $t('pos.catalog_load_more') }}
          </button>
        </div>
      </template>

      <!-- Empty State -->
      <EmptyState
        v-else
        :icon="PackageSearch"
        :title="searchQuery ? $t('pos.no_items_match_search') : $t('pos.no_items_in_category')"
        class="py-12"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Search, Star, Store, Folder, PackageSearch, Sparkles, PackageCheck, AlertTriangle } from 'lucide-vue-next';
import POSProductButton from './POSProductButton.vue';
import DynamicIcon from '../Common/DynamicIcon.vue';
import EmptyState from '../Common/EmptyState.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  items: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  activeCategoryId: { type: [Number, String, null], default: null },
  activePriceTier: { type: String, default: 'retail' },
  searchQuery: { type: String, default: '' },
  visibleCount: { type: Number, default: 0 },
  isLoading: { type: Boolean, default: false },
  isLoadingMore: { type: Boolean, default: false },
  hasMore: { type: Boolean, default: false },
  cardsPerRow: { type: String, default: '3' },
  showImages: { type: Boolean, default: false },
});

const emit = defineEmits(['add-item', 'load-more']);

// Static class strings so Tailwind keeps them. 2 and 3 fall back by panel width; "auto" fills with a minimum card width.
const GRID_CLASSES = {
  2: 'grid-cols-1 @[340px]:grid-cols-2',
  3: 'grid-cols-1 @[340px]:grid-cols-2 @[600px]:grid-cols-3',
};
const gridClass = computed(() =>
  props.cardsPerRow === 'auto' ? '' : GRID_CLASSES[props.cardsPerRow] || GRID_CLASSES[3]
);
const gridStyle = computed(() =>
  props.cardsPerRow === 'auto' ? { gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))' } : {}
);

const scrollRef = ref(null);
const sentinelRef = ref(null);
let observer = null;

const observeSentinel = () => {
  observer?.disconnect();
  if (!observer || !sentinelRef.value) return;
  observer.observe(sentinelRef.value);
};

onMounted(() => {
  if (typeof IntersectionObserver === 'undefined') return;
  observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting) && props.hasMore && !props.isLoadingMore) emit('load-more');
    },
    { root: scrollRef.value, rootMargin: '240px' }
  );
  observeSentinel();
});

// The sentinel is re-created with the list; observing again also re-fires when it is still in view.
watch(
  () => [props.items.length, props.isLoading, props.isLoadingMore],
  () => setTimeout(observeSentinel, 0),
  {
    flush: 'post',
  }
);

onBeforeUnmount(() => observer?.disconnect());

const categoryOf = (item) => props.categories.find((c) => c.id === item.category_id || c.name === item.category);
const getCategoryColor = (item) => item.category_color || categoryOf(item)?.color || '#64748B';

const activeCategoryObject = computed(() => {
  if (!props.activeCategoryId || typeof props.activeCategoryId === 'string') return null;
  return props.categories.find((c) => c.id === props.activeCategoryId);
});

const activeCategoryName = computed(() => {
  if (props.searchQuery) return t('pos.search_results_title');
  if (props.activeCategoryId === 'favorites') return t('pos.favorites_tab');
  if (props.activeCategoryId === 'newest') return t('pos.newest_tab');
  if (props.activeCategoryId === 'in_stock') return t('pos.in_stock_tab');
  if (props.activeCategoryId === 'low_stock') return t('pos.low_stock_tab');
  if (props.activeCategoryId === null) return t('pos.all_items_tab');
  return activeCategoryObject.value?.name || t('pos.all_items_tab');
});

const activeCategoryIcon = computed(() => {
  if (props.searchQuery) return Search;
  if (props.activeCategoryId === 'favorites') return Star;
  if (props.activeCategoryId === 'newest') return Sparkles;
  if (props.activeCategoryId === 'in_stock') return PackageCheck;
  if (props.activeCategoryId === 'low_stock') return AlertTriangle;
  if (props.activeCategoryId === null) return Store;
  return activeCategoryObject.value?.icon || Folder;
});
</script>
