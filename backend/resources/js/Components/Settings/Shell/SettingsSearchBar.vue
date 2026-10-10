<template>
  <div class="relative w-full max-w-md font-tajawal" ref="searchContainer">
    <!-- Search Input -->
    <div class="relative flex items-center">
      <div class="absolute start-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
        <Search class="w-4 h-4" />
      </div>

      <input
        type="text"
        v-model="query"
        @focus="isOpen = true"
        @keydown.esc="closeSearch"
        @keydown.down.prevent="navigateResults(1)"
        @keydown.up.prevent="navigateResults(-1)"
        @keydown.enter.prevent="selectHighlighted"
        :placeholder="$t('settings.search_placeholder')"
        class="w-full min-h-[44px] ps-10 pe-10 text-xs sm:text-sm bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-2xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-theme-primary focus:border-transparent transition shadow-xs"
        dir="auto"
      />

      <button
        v-if="query"
        type="button"
        @click="clearQuery"
        class="absolute end-2.5 min-w-[32px] min-h-[32px] flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95"
      >
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Dropdown Results -->
    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="transform opacity-0 scale-95 -translate-y-1"
      enter-to-class="transform opacity-100 scale-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="transform opacity-100 scale-100 translate-y-0"
      leave-to-class="transform opacity-0 scale-95 -translate-y-1"
    >
      <div
        v-if="isOpen && query.trim()"
        class="absolute z-50 mt-2 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl overflow-hidden max-h-80 overflow-y-auto"
      >
        <!-- Results List -->
        <div v-if="filteredResults.length > 0" class="p-1.5 space-y-1">
          <button
            v-for="(item, index) in filteredResults"
            :key="item.id"
            type="button"
            @click="chooseItem(item)"
            @mouseenter="highlightedIndex = index"
            class="w-full text-start p-3 rounded-xl transition flex items-start gap-3 cursor-pointer min-h-[44px]"
            :class="
              highlightedIndex === index
                ? 'bg-theme-light dark:bg-slate-800/80 ring-1 ring-theme-primary/30'
                : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'
            "
          >
            <!-- Tab Badge Indicator -->
            <div
              class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0 mt-0.5 text-theme-primary"
            >
              <component :is="getTabIcon(item.tabId)" class="w-4 h-4" />
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-black text-slate-900 dark:text-white truncate">
                  {{ item.label }}
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"
                >
                  {{ getTabLabel(item.tabId) }}
                </span>
              </div>

              <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5">
                {{ item.description }}
              </p>

              <div class="flex items-center gap-2 mt-1">
                <span class="font-mono text-[10px] text-slate-400 dark:text-slate-500 truncate" dir="ltr">
                  #{{ item.key }}
                </span>
                <span
                  v-if="item.isAdvanced"
                  class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                >
                  {{ $t('settings.advanced_badge') }}
                </span>
              </div>
            </div>
          </button>
        </div>

        <!-- Empty State -->
        <div v-else class="p-6 text-center text-slate-500 dark:text-slate-400 space-y-1">
          <p class="text-xs font-bold">{{ $t('settings.search_no_results') }}</p>
          <p class="text-[11px] text-slate-400">"{{ query }}"</p>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Search, X, Building2, Printer, Package, Bot, Laptop } from 'lucide-vue-next';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  results: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'select']);

const { t } = useTrans();
const searchContainer = ref(null);
const isOpen = ref(false);
const highlightedIndex = ref(0);

const query = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const filteredResults = computed(() => props.results);

watch(filteredResults, (newResults) => {
  highlightedIndex.value = 0;
  if (newResults.length > 0 && query.value.trim()) {
    isOpen.value = true;
  }
});

const getTabIcon = (tabId) => {
  switch (tabId) {
    case 'general':
      return Building2;
    case 'printing':
      return Printer;
    case 'inventory':
      return Package;
    case 'notifications':
      return Bot;
    case 'device':
    default:
      return Laptop;
  }
};

const getTabLabel = (tabId) => {
  switch (tabId) {
    case 'general':
      return t('settings.tab_general');
    case 'printing':
      return t('settings.sec_printing_label');
    case 'inventory':
      return t('settings.tab_inventory');
    case 'notifications':
      return t('settings.tab_notifications');
    case 'device':
      return t('settings.tab_device');
    default:
      return tabId;
  }
};

const clearQuery = () => {
  emit('update:modelValue', '');
  isOpen.value = false;
};

const closeSearch = () => {
  isOpen.value = false;
};

const navigateResults = (direction) => {
  if (filteredResults.value.length === 0) return;
  const newIndex = highlightedIndex.value + direction;
  if (newIndex >= 0 && newIndex < filteredResults.value.length) {
    highlightedIndex.value = newIndex;
  }
};

const selectHighlighted = () => {
  if (filteredResults.value.length > 0 && filteredResults.value[highlightedIndex.value]) {
    chooseItem(filteredResults.value[highlightedIndex.value]);
  }
};

const chooseItem = (item) => {
  emit('select', item);
  isOpen.value = false;
};

const handleClickOutside = (e) => {
  if (searchContainer.value && !searchContainer.value.contains(e.target)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  if (typeof document !== 'undefined') {
    document.addEventListener('click', handleClickOutside);
  }
});

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.removeEventListener('click', handleClickOutside);
  }
});
</script>
