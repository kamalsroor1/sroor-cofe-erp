<script setup>
import POSProductGrid from './POSProductGrid.vue';
import POSCategorySidebar from './POSCategorySidebar.vue';

defineProps({
  items: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  activeCategoryId: { type: [String, Number], default: null },
  activePriceTier: { type: String, default: 'retail' },
  searchQuery: { type: String, default: '' },
  favoriteCount: { type: Number, default: 0 },
  totalItemsCount: { type: Number, default: 0 },
  visibleCount: { type: Number, default: 0 },
  isLoading: { type: Boolean, default: false },
  isLoadingMore: { type: Boolean, default: false },
  hasMore: { type: Boolean, default: false },
  cardsPerRow: { type: String, default: '3' },
  showImages: { type: Boolean, default: false },
});

defineEmits(['add-item', 'select-category', 'load-more']);
</script>

<template>
  <main
    class="w-full lg:w-[48%] xl:w-[54%] 2xl:w-[58%] flex flex-col overflow-visible lg:overflow-hidden shrink-0 bg-slate-100/60 dark:bg-slate-900/60 border-b lg:border-b-0 lg:border-e border-slate-200 dark:border-slate-800 order-1 lg:order-2 animate-in fade-in duration-150 min-h-[300px] lg:min-h-0"
  >
    <POSProductGrid
      :items="items"
      :categories="categories"
      :active-category-id="activeCategoryId"
      :active-price-tier="activePriceTier"
      :search-query="searchQuery"
      :visible-count="visibleCount"
      :is-loading="isLoading"
      :is-loading-more="isLoadingMore"
      :has-more="hasMore"
      :cards-per-row="cardsPerRow"
      :show-images="showImages"
      @add-item="$emit('add-item', $event)"
      @load-more="$emit('load-more')"
    />
  </main>

  <POSCategorySidebar
    class="order-3 animate-in fade-in duration-150 shrink-0"
    :categories="categories"
    :active-category-id="activeCategoryId"
    :favorite-count="favoriteCount"
    :total-items-count="totalItemsCount"
    @select-category="$emit('select-category', $event)"
  />
</template>
