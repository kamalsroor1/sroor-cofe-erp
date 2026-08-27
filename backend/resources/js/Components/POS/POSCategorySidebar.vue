<template>
  <aside class="h-full flex flex-col bg-white dark:bg-slate-900/95 border-s border-slate-200 dark:border-slate-800 overflow-hidden w-14 sm:w-36 md:w-40 shrink-0">
    <!-- Header -->
    <div class="p-2.5 border-b border-slate-200 dark:border-slate-800">
      <h3 class="text-xs font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden sm:block">{{ $t('pos.categories') }}</h3>
      <div class="flex justify-center sm:hidden">
        <Folder class="w-4 h-4 text-slate-400" />
      </div>
    </div>
    
    <!-- Scrollable Categories -->
    <nav class="flex-1 overflow-y-auto custom-scrollbar p-1.5 space-y-1" dir="rtl">
      <!-- 1. Favorites Tab -->
      <button 
        @click="$emit('select-category', 'favorites')"
        class="w-full flex items-center justify-center sm:justify-between rounded-xl p-2 sm:p-2.5 cursor-pointer transition-all duration-150 border-e-4"
        :class="activeCategoryId === 'favorites' ? 'bg-[#fef3c7] dark:bg-amber-500/20 border-amber-500 font-bold' : 'bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border-transparent'"
        :title="$t('pos.favorites_tab')"
      >
        <span class="flex items-center gap-1.5 truncate">
          <Star class="w-4 h-4 text-amber-500 fill-amber-400 shrink-0" /> 
          <span class="hidden sm:inline text-xs font-bold truncate">{{ $t('pos.favorites_tab') }}</span>
        </span>
        <span v-if="favoriteCount > 0" class="badge bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 px-1.5 py-0.2 rounded text-[10px] font-mono hidden sm:inline-block">{{ favoriteCount }}</span>
      </button>

      <!-- 2. Newest Arrivals Tab -->
      <button 
        @click="$emit('select-category', 'newest')"
        class="w-full flex items-center justify-center sm:justify-between rounded-xl p-2 sm:p-2.5 cursor-pointer transition-all duration-150 border-e-4"
        :class="activeCategoryId === 'newest' ? 'bg-emerald-50 dark:bg-emerald-500/20 border-emerald-500 font-bold' : 'bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border-transparent'"
        :title="$t('pos.newest_tab')"
      >
        <span class="flex items-center gap-1.5 truncate">
          <Sparkles class="w-4 h-4 text-emerald-500 shrink-0" /> 
          <span class="hidden sm:inline text-xs font-bold truncate">{{ $t('pos.newest_tab') }}</span>
        </span>
      </button>

      <!-- 3. In Stock Only Tab -->
      <button 
        @click="$emit('select-category', 'in_stock')"
        class="w-full flex items-center justify-center sm:justify-between rounded-xl p-2 sm:p-2.5 cursor-pointer transition-all duration-150 border-e-4"
        :class="activeCategoryId === 'in_stock' ? 'bg-sky-50 dark:bg-sky-500/20 border-sky-500 font-bold' : 'bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border-transparent'"
        :title="$t('pos.in_stock_tab')"
      >
        <span class="flex items-center gap-1.5 truncate">
          <PackageCheck class="w-4 h-4 text-sky-500 shrink-0" /> 
          <span class="hidden sm:inline text-xs font-bold truncate">{{ $t('pos.in_stock_tab') }}</span>
        </span>
      </button>

      <!-- 4. Low Stock Alert Tab -->
      <button 
        @click="$emit('select-category', 'low_stock')"
        class="w-full flex items-center justify-center sm:justify-between rounded-xl p-2 sm:p-2.5 cursor-pointer transition-all duration-150 border-e-4"
        :class="activeCategoryId === 'low_stock' ? 'bg-rose-50 dark:bg-rose-500/20 border-rose-500 font-bold' : 'bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border-transparent'"
        :title="$t('pos.low_stock_tab')"
      >
        <span class="flex items-center gap-1.5 truncate">
          <AlertTriangle class="w-4 h-4 text-rose-500 shrink-0" /> 
          <span class="hidden sm:inline text-xs font-bold truncate">{{ $t('pos.low_stock_tab') }}</span>
        </span>
      </button>

      <!-- Divider -->
      <div v-if="categories.length > 0" class="my-1.5 border-t border-slate-200 dark:border-slate-800"></div>

      <!-- Dynamic Categories from Database -->
      <button 
        v-for="cat in categories" :key="cat.id" 
        @click="$emit('select-category', cat.id)"
        class="w-full flex items-center justify-center sm:justify-between rounded-xl p-2 sm:p-2.5 cursor-pointer transition-all duration-150 border-e-4"
        :style="activeCategoryId === cat.id ? { backgroundColor: cat.color_light || `${cat.color}20`, borderInlineEndColor: cat.color || 'var(--color-primary)' } : {}"
        :class="activeCategoryId === cat.id ? 'font-bold' : 'bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border-transparent'"
        :title="cat.name"
      >
        <span class="flex items-center gap-1.5 truncate">
          <DynamicIcon :name="cat.icon" class="w-4 h-4 shrink-0" /> 
          <span class="truncate hidden sm:inline text-xs font-bold">{{ cat.name }}</span>
        </span>
        <span class="badge bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 px-1.5 py-0.2 rounded text-[10px] font-mono hidden sm:inline-block">{{ cat.items_count ?? 0 }}</span>
      </button>
    </nav>
    
    <!-- Footer: All Items -->
    <div class="p-2 border-t border-slate-200 dark:border-slate-800">
      <button 
        @click="$emit('select-category', null)"
        class="w-full text-center p-2 rounded-xl text-xs sm:text-sm transition-all duration-150 flex items-center justify-center gap-1"
        :class="activeCategoryId === null ? 'bg-slate-200 dark:bg-slate-700 font-bold text-slate-800 dark:text-slate-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'"
        :title="$t('common.all_items')"
      >
        <span class="hidden sm:inline">{{ $t('common.all_items') }} ({{ totalCount }})</span>
        <span class="inline sm:hidden flex justify-center"><Store class="w-4 h-4" /></span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { Folder, Star, Store, Sparkles, PackageCheck, AlertTriangle } from 'lucide-vue-next';
import DynamicIcon from '../Common/DynamicIcon.vue';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  activeCategoryId: { type: [Number, String, null], default: null },
  favoriteCount: { type: Number, default: 0 },
  totalItemsCount: { type: Number, default: 0 },
});

defineEmits(['select-category']);

const totalCount = computed(() => {
  if (props.totalItemsCount > 0) return props.totalItemsCount;
  return props.categories.reduce((sum, cat) => sum + (cat.items_count || 0), 0);
});
</script>
