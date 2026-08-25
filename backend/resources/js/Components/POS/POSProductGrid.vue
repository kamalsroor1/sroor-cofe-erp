<template>
  <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-950" dir="rtl">
    <!-- Grid Header: category name + count -->
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 shrink-0">
      <div class="flex items-center gap-2">
        <DynamicIcon :name="activeCategoryIcon" class="w-4 h-4 text-theme-primary shrink-0" />
        <h2 class="text-sm font-black text-slate-700 dark:text-slate-200">{{ activeCategoryName }}</h2>
        <span class="text-xs font-mono bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded-lg">{{ filteredItems.length }}</span>
      </div>
      <!-- Page indicator -->
      <div v-if="totalPages > 1" class="flex items-center gap-2 text-xs">
        <button @click="prevPage" :disabled="currentPage <= 1" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800 disabled:opacity-50">
          <ChevronRight class="w-4 h-4" />
        </button>
        <span class="font-mono font-bold text-slate-500">{{ currentPage }} / {{ totalPages }}</span>
        <button @click="nextPage" :disabled="currentPage >= totalPages" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800 disabled:opacity-50">
          <ChevronLeft class="w-4 h-4" />
        </button>
      </div>
    </div>
    
    <!-- Products Grid -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-3">
      <div v-if="paginatedItems.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 gap-2.5">
        <POSProductButton
          v-for="item in paginatedItems"
          :key="item.id"
          :item="item"
          :category-color="getCategoryColor(item)"
          :category-color-light="getCategoryColorLight(item)"
          :active-price-tier="activePriceTier"
          @add-item="$emit('add-item', $event)"
        />
      </div>
      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center h-full text-center py-12">
        <PackageSearch class="w-12 h-12 text-slate-300 dark:text-slate-700 mb-3 stroke-[1.5]" />
        <p class="text-sm font-bold text-slate-500 dark:text-slate-400">{{ $t('pos.no_items_in_category') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { ChevronRight, ChevronLeft, Search, Star, Store, Folder, PackageSearch } from 'lucide-vue-next';
import POSProductButton from './POSProductButton.vue';
import DynamicIcon from '../Common/DynamicIcon.vue';

const props = defineProps({
  items: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  activeCategoryId: { type: [Number, String, null], default: null },
  activePriceTier: { type: String, default: 'retail' },
  searchQuery: { type: String, default: '' },
});

defineEmits(['add-item']);

const ITEMS_PER_PAGE = 30;
const currentPage = ref(1);

const filteredItems = computed(() => {
  let result = props.items;
  
  if (props.searchQuery) {
    const q = props.searchQuery.toLowerCase();
    result = result.filter(item => 
      (item.name && item.name.toLowerCase().includes(q)) || 
      (item.code && item.code.toLowerCase().includes(q))
    );
  } else if (props.activeCategoryId === 'favorites') {
    result = [...result]
      .sort((a, b) => (b.pos_sales_count || 0) - (a.pos_sales_count || 0))
      .slice(0, 20);
  } else if (props.activeCategoryId !== null) {
    result = result.filter(item => item.category_id === props.activeCategoryId);
  }
  
  return result;
});

const totalPages = computed(() => Math.ceil(filteredItems.value.length / ITEMS_PER_PAGE) || 1);

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * ITEMS_PER_PAGE;
  const end = start + ITEMS_PER_PAGE;
  return filteredItems.value.slice(start, end);
});

const prevPage = () => {
  if (currentPage.value > 1) currentPage.value--;
};

const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value++;
};

watch([() => props.activeCategoryId, () => props.searchQuery], () => {
  currentPage.value = 1;
});

const getCategoryColor = (item) => {
  const cat = props.categories.find(c => c.id === item.category_id);
  return cat?.color || '#64748B';
};

const getCategoryColorLight = (item) => {
  const cat = props.categories.find(c => c.id === item.category_id);
  return cat?.color_light || '#f1f5f9';
};

const activeCategoryObject = computed(() => {
  if (!props.activeCategoryId || props.activeCategoryId === 'favorites') return null;
  return props.categories.find(c => c.id === props.activeCategoryId);
});

const activeCategoryName = computed(() => {
  if (props.searchQuery) return 'نتائج البحث';
  if (props.activeCategoryId === 'favorites') return 'الأكثر مبيعاً';
  if (props.activeCategoryId === null) return 'جميع المنتجات';
  return activeCategoryObject.value?.name || 'غير معروف';
});

const activeCategoryIcon = computed(() => {
  if (props.searchQuery) return Search;
  if (props.activeCategoryId === 'favorites') return Star;
  if (props.activeCategoryId === null) return Store;
  return activeCategoryObject.value?.icon || Folder;
});
</script>
