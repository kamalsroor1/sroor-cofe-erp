<template>
  <aside class="h-full flex flex-col bg-white dark:bg-slate-900/95 border-s border-slate-200 dark:border-slate-800 overflow-hidden w-16 sm:w-48 shrink-0">
    <!-- Header -->
    <div class="p-3 border-b border-slate-200 dark:border-slate-800">
      <h3 class="text-xs font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden sm:block">{{ $t('pos.categories') }}</h3>
      <div class="flex justify-center sm:hidden">
        <Folder class="w-4 h-4 text-slate-400" />
      </div>
    </div>
    
    <!-- Scrollable Categories -->
    <nav class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1.5" dir="rtl">
      <!-- Favorites Tab (always first) -->
      <button 
        @click="$emit('select-category', 'favorites')"
        class="w-full flex items-center justify-center sm:justify-between rounded-xl p-3 cursor-pointer transition-all duration-150 border-e-4"
        :class="activeCategoryId === 'favorites' ? 'bg-[#fef3c7] dark:bg-amber-500/20 border-amber-500 font-bold' : 'bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border-transparent'"
        :title="$t('pos.favorites_tab')"
      >
        <span class="flex items-center gap-2">
          <Star class="w-4 h-4 text-amber-500 fill-amber-400 shrink-0" /> 
          <span class="hidden sm:inline">{{ $t('pos.favorites_tab') }}</span>
        </span>
        <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 px-1.5 py-0.5 rounded text-xs hidden sm:inline-block">{{ favoriteCount }}</span>
      </button>
      
      <!-- Dynamic Categories -->
      <button 
        v-for="cat in categories" :key="cat.id" 
        @click="$emit('select-category', cat.id)"
        class="w-full flex items-center justify-center sm:justify-between rounded-xl p-3 cursor-pointer transition-all duration-150 border-e-4"
        :style="activeCategoryId === cat.id ? { backgroundColor: cat.color_light || `${cat.color}20`, borderInlineEndColor: cat.color } : {}"
        :class="activeCategoryId === cat.id ? 'font-bold' : 'bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border-transparent'"
        :title="cat.name"
      >
        <span class="flex items-center gap-2 truncate">
          <DynamicIcon :name="cat.icon" class="w-4 h-4 shrink-0" /> 
          <span class="truncate hidden sm:inline">{{ cat.name }}</span>
        </span>
        <span class="badge bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 px-1.5 py-0.5 rounded text-xs hidden sm:inline-block">{{ cat.items_count }}</span>
      </button>
    </nav>
    
    <!-- Footer: All Items -->
    <div class="p-2 border-t border-slate-200 dark:border-slate-800">
      <button 
        @click="$emit('select-category', null)"
        class="w-full text-center p-2 rounded-xl text-sm transition-all duration-150"
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
import { Folder, Star, Store } from 'lucide-vue-next';
import DynamicIcon from '../Common/DynamicIcon.vue';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  activeCategoryId: { type: [Number, String, null], default: null },
  favoriteCount: { type: Number, default: 0 }
});

defineEmits(['select-category']);

const totalCount = computed(() => {
  return props.categories.reduce((sum, cat) => sum + (cat.items_count || 0), 0);
});
</script>
