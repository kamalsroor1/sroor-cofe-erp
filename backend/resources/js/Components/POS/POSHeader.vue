<template>
  <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-2.5 shrink-0 shadow-xs z-30 font-tajawal select-none">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
      
      <!-- Right: Logo & Quick Navigation -->
      <div class="flex items-center gap-3 shrink-0">
        <router-link
          to="/invoices"
          class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition border border-slate-200 dark:border-slate-700 cursor-pointer"
          :title="$t('pos.back_to_invoices')"
        >
          <ArrowRight class="w-4 h-4" />
        </router-link>

        <div class="flex items-center gap-2">
          <div class="w-10 h-10 rounded-xl bg-theme-primary text-slate-950 flex items-center justify-center font-black text-lg shadow-xs">
            ⚡
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-sm font-black text-slate-900 dark:text-white leading-none">{{ $t('pos.pos_fast_title') }}</h1>
              <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded bg-theme-light text-theme-primary">v{{ appVersion }}</span>
            </div>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">{{ activeStore?.name || $t('common.main_branch') }}</span>
              <span class="text-slate-300 dark:text-slate-700">•</span>
              <span v-if="activeShift" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ $t('pos.shift_number_badge', { number: activeShift.shift_number || activeShift.id }) }}
              </span>
              <span v-else class="text-[10px] text-rose-500 font-bold">{{ $t('pos.no_shift_open') }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Center: Giant Barcode / Product Search Box with Dropdown -->
      <div class="flex-1 max-w-3xl relative">
        <div class="relative">
          <div class="absolute inset-y-0 start-0 ps-3.5 flex items-center pointer-events-none text-slate-400">
            <Search class="w-5 h-5" />
          </div>
          <input
            ref="searchInputRef"
            :value="searchQuery"
            @input="$emit('update:searchQuery', $event.target.value)"
            type="text"
            class="w-full h-12 ps-11 pe-24 bg-slate-50 dark:bg-slate-950 border-2 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-4 focus:ring-theme-primary/20 rounded-2xl text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 transition-all focus:outline-none"
            :placeholder="$t('pos.search_placeholder_long')"
            @keydown.down.prevent="$emit('navigate-dropdown', 'down')"
            @keydown.up.prevent="$emit('navigate-dropdown', 'up')"
            @keydown.enter.prevent="$emit('select-highlighted')"
            @keydown.esc="$emit('close-dropdown')"
            @focus="$emit('update:isSearchFocused', true)"
          />
          
          <div class="absolute inset-y-0 end-0 pe-2 flex items-center gap-1.5">
            <button
              v-if="searchQuery"
              type="button"
              @click="$emit('update:searchQuery', ''); focusSearch()"
              class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg cursor-pointer"
            >
              ✕
            </button>
            <kbd class="hidden sm:inline-block px-2 py-1 text-[10px] font-mono font-bold bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-lg border border-slate-300 dark:border-slate-700">F2</kbd>
          </div>
        </div>

        <!-- 🌟 FLOATING LIVE SEARCH RESULTS DROPDOWN -->
        <div
          v-if="isSearchFocused && searchResults.length > 0"
          class="absolute top-full start-0 end-0 mt-2 bg-white dark:bg-slate-900 border-2 border-theme-primary/50 rounded-2xl shadow-2xl overflow-hidden z-50 max-h-96 overflow-y-auto custom-scrollbar animate-in fade-in slide-in-from-top-2 duration-150"
        >
          <div class="p-2 bg-slate-50 dark:bg-slate-950/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-[11px] font-bold text-slate-500">
            <span>{{ $t('pos.search_results_count', { count: searchResults.length }) }} • {{ $t('pos.search_nav_hint') }}</span>
            <span class="text-theme-primary">{{ $t('pos.prices_label') }}: {{ activePriceTier === 'wholesale' ? $t('pos.wholesale_short') : $t('pos.retail_short') }}</span>
          </div>

          <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
            <button
              v-for="(item, idx) in searchResults"
              :key="item.id"
              type="button"
              @click="$emit('add-item', item)"
              @mouseenter="$emit('update:highlightedIndex', idx)"
              class="w-full p-3 flex items-center justify-between text-start transition-all cursor-pointer group"
              :class="highlightedIndex === idx
                ? 'bg-theme-light dark:bg-slate-800 text-slate-950 dark:text-white ring-1 ring-inset ring-theme-primary'
                : 'hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-800 dark:text-slate-200'"
            >
              <!-- Item Info -->
              <div class="flex items-center gap-3 min-w-0 flex-1">
                <div
                  class="w-9 h-9 rounded-xl flex items-center justify-center font-mono font-bold text-xs shrink-0"
                  :class="highlightedIndex === idx ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
                >
                  +
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-black text-sm text-slate-950 dark:text-white truncate flex items-center gap-2">
                    <span>{{ item.name }}</span>
                    <span v-if="item.category" class="text-[10px] font-normal px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 font-tajawal">{{ item.category }}</span>
                  </div>
                  <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500 font-mono">
                    <span class="font-bold text-slate-400">{{ item.code || '—' }}</span>
                    <span>•</span>
                    <span
                      class="font-bold px-1.5 py-0.2 rounded text-[11px]"
                      :class="item.current_stock > 0 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' : 'text-rose-500 bg-rose-500/10'"
                    >
                      {{ item.current_stock > 0 ? $t('pos.available_stock_badge', { qty: formatMoney(item.current_stock), unit: item.unit || '' }) : $t('pos.out_of_stock_badge', { qty: formatMoney(item.current_stock), unit: item.unit || '' }) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Prices Breakdown & Action -->
              <div class="flex items-center gap-4 shrink-0 ms-3 text-end">
                <div class="space-y-0.5">
                  <div v-if="item.price_wholesale || item.min_selling_price" class="text-[10px] font-mono text-purple-600 dark:text-purple-400 font-bold">
                    {{ $t('pos.min_selling_price') }}: {{ formatMoney(item.min_selling_price || item.price_wholesale) }}
                  </div>
                  <div class="text-base font-black font-mono text-emerald-600 dark:text-emerald-400">
                    {{ formatMoney(getItemPrice(item)) }} <span class="text-[10px] font-normal text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
                  </div>
                </div>

                <span class="hidden sm:inline-block text-xs font-bold px-2.5 py-1 rounded-lg bg-theme-primary text-slate-950 group-hover:scale-105 transition-transform shadow-xs">
                  {{ $t('pos.add_item_btn') }}
                </span>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Left: Customer, Tier & Action Buttons -->
      <div class="flex items-center gap-2 shrink-0">
        <!-- Customer Selection Button -->
        <button
          type="button"
          @click="$emit('open-customer-picker')"
          class="min-h-[44px] px-3 py-2 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700/80 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-2xs"
        >
          <Users class="w-4 h-4 text-theme-primary shrink-0" />
          <div class="text-start leading-tight min-w-0">
            <div class="text-slate-900 dark:text-white truncate max-w-[130px] font-black">{{ selectedCustomer?.name || $t('pos.general_cash_customer') }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ selectedCustomer?.phone || $t('pos.general_cash_customer') }}</div>
          </div>
        </button>

        <!-- Price Tier Toggle (Retail / Wholesale) -->
        <div class="flex p-0.5 bg-slate-200 dark:bg-slate-800 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold">
          <button
            type="button"
            @click="$emit('update:activePriceTier', 'retail')"
            class="min-h-[36px] px-2.5 py-1 rounded-lg transition cursor-pointer"
            :class="activePriceTier === 'retail' ? 'bg-white dark:bg-slate-900 text-theme-primary font-black shadow-xs' : 'text-slate-600 dark:text-slate-400'"
          >
            {{ $t('pos.retail_short') }}
          </button>
          <button
            type="button"
            @click="$emit('update:activePriceTier', 'wholesale')"
            class="min-h-[36px] px-2.5 py-1 rounded-lg transition cursor-pointer"
            :class="activePriceTier === 'wholesale' ? 'bg-white dark:bg-slate-900 text-purple-500 font-black shadow-xs' : 'text-slate-600 dark:text-slate-400'"
          >
            {{ $t('pos.wholesale_short') }}
          </button>
        </div>

        <!-- Clear Cart -->
        <button
          type="button"
          @click="$emit('clear-cart')"
          :disabled="cartEmpty"
          class="min-h-[44px] min-w-[44px] p-2.5 bg-slate-100 hover:bg-rose-500/20 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700 rounded-xl transition disabled:opacity-30 cursor-pointer flex items-center justify-center"
          :title="$t('pos.clear_cart_full')"
        >
          <RotateCcw class="w-4 h-4" />
        </button>
      </div>

    </div>
  </header>
</template>

<script setup>
import { ref } from 'vue';
import { ArrowRight, Search, Users, RotateCcw } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();
const searchInputRef = ref(null);

const props = defineProps({
  appVersion: { type: String, default: '1.0.10' },
  activeStore: { type: Object, default: null },
  activeShift: { type: Object, default: null },
  searchQuery: { type: String, default: '' },
  isSearchFocused: { type: Boolean, default: false },
  searchResults: { type: Array, default: () => [] },
  highlightedIndex: { type: Number, default: 0 },
  activePriceTier: { type: String, default: 'retail' },
  selectedCustomer: { type: Object, default: null },
  cartEmpty: { type: Boolean, default: true },
});

const emit = defineEmits([
  'update:searchQuery',
  'update:isSearchFocused',
  'update:highlightedIndex',
  'update:activePriceTier',
  'add-item',
  'navigate-dropdown',
  'select-highlighted',
  'close-dropdown',
  'open-customer-picker',
  'clear-cart',
]);

const getItemPrice = (item) => {
  return props.activePriceTier === 'wholesale' ? (item.price_wholesale || item.price_retail) : item.price_retail;
};

const focusSearch = () => searchInputRef.value?.focus();

defineExpose({ focusSearch });
</script>
