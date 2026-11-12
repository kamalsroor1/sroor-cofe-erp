<script setup>
import POSProductGrid from './POSProductGrid.vue';
import POSCategorySidebar from './POSCategorySidebar.vue';
import POSCategoryChips from './POSCategoryChips.vue';

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
  <!-- Below xl the categories are a chip row on top of the grid; from xl up they move to the side rail -->
  <main
    class="order-1 lg:order-2 flex-1 min-w-0 min-h-0 w-full flex flex-col overflow-hidden bg-slate-100/60 dark:bg-slate-900/60 border-slate-200 dark:border-slate-800 lg:border-e animate-in fade-in duration-150"
  >
    <POSCategoryChips
      class="xl:hidden"
      :categories="categories"
      :active-category-id="activeCategoryId"
      @select-category="$emit('select-category', $event)"
    />

    <div class="flex-1 min-h-0">
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
    </div>
  </main>

  <div class="hidden xl:flex order-3 shrink-0 min-h-0 animate-in fade-in duration-150">
    <POSCategorySidebar
      :categories="categories"
      :active-category-id="activeCategoryId"
      :favorite-count="favoriteCount"
      :total-items-count="totalItemsCount"
      @select-category="$emit('select-category', $event)"
    />
  </div>
</template>
