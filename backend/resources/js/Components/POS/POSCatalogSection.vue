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
});

defineEmits(['add-item', 'select-category']);
</script>

<template>
  <main
    class="w-full lg:w-[350px] xl:w-[400px] 2xl:w-[440px] flex flex-col overflow-visible lg:overflow-hidden shrink-0 bg-slate-100/60 dark:bg-slate-900/60 border-b lg:border-b-0 lg:border-e border-slate-200 dark:border-slate-800 order-1 lg:order-2 animate-in fade-in duration-150 min-h-[300px] lg:min-h-0"
  >
    <POSProductGrid
      :items="items"
      :categories="categories"
      :active-category-id="activeCategoryId"
      :active-price-tier="activePriceTier"
      :search-query="searchQuery"
      @add-item="$emit('add-item', $event)"
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
