<template>
  <header
    class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-2 py-[5px] md:px-4 md:py-2.5 shrink-0 shadow-xs z-30 font-tajawal select-none"
  >
    <div class="flex items-center justify-between gap-2 md:gap-3">
      <!-- Right: Logo & Quick Navigation -->
      <div class="flex items-center gap-3 shrink-0">
        <router-link
          to="/invoices"
          class="w-11 h-11 md:w-10 md:h-10 pointer-coarse:w-11 pointer-coarse:h-11 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition border border-slate-200 dark:border-slate-700 cursor-pointer shrink-0"
          :title="$t('pos.back_to_invoices')"
          :aria-label="$t('pos.back_to_invoices')"
          data-testid="pos-back-link"
        >
          <ArrowRight class="w-4 h-4 ltr:-scale-x-100" aria-hidden="true" />
        </router-link>

        <div class="hidden md:flex items-center gap-2 min-w-0">
          <div
            class="hidden xl:flex w-10 h-10 rounded-xl bg-theme-primary text-slate-950 items-center justify-center font-black text-lg shadow-xs"
          >
            <Zap class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <h1 class="hidden xl:block text-sm font-black text-slate-900 dark:text-white leading-none">
              {{ $t('pos.pos_fast_title') }}
            </h1>
            <div class="flex items-center gap-2 xl:mt-1">
              <!-- Store Switcher directly inside POS Header -->
              <div v-if="stores?.length > 1" class="relative inline-flex items-center">
                <select
                  :value="activeStore?.id"
                  @change="$emit('switch-store', $event.target.value)"
                  class="h-6 pointer-coarse:h-11 pointer-coarse:-my-2.5 pe-5 ps-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-800 dark:text-cyan-400 focus:outline-none focus:ring-1 focus:ring-theme-primary cursor-pointer font-tajawal appearance-none transition"
                >
                  <option
                    v-for="s in stores"
                    :key="s.id"
                    :value="s.id"
                    class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white"
                  >
                    {{ s.name }}
                  </option>
                </select>
                <ChevronDown class="w-3 h-3 text-slate-400 absolute end-1 pointer-events-none" />
              </div>
              <span v-else class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{
                activeStore?.name || $t('common.main_branch')
              }}</span>
              <span class="text-slate-300 dark:text-slate-700">•</span>
              <!-- POS-10: the shift badge opens the shift menu (X-report / close, or open when none) -->
              <button
                type="button"
                class="min-h-[44px] px-2 -my-2 rounded-lg text-xs font-bold flex items-center gap-1.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                :class="activeShift ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'"
                :aria-label="$t('pos.shift_menu')"
                data-testid="pos-shift-badge"
                @click="$emit('open-shift-menu')"
              >
                <template v-if="activeShift">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  <span class="truncate max-w-[104px] xl:max-w-none">{{
                    $t('pos.shift_number_badge', { number: activeShift.shift_number || activeShift.id })
                  }}</span>
                </template>
                <template v-else>{{ $t('pos.no_shift_open') }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Center: Giant Barcode / Product Search Box with Dropdown -->
      <div class="flex-1 min-w-0 md:min-w-[280px] max-w-3xl relative" data-testid="pos-search-field">
        <div class="relative">
          <div class="absolute inset-y-0 start-0 ps-3.5 flex items-center pointer-events-none text-slate-400">
            <Search class="w-5 h-5" />
          </div>
          <input
            ref="searchInputRef"
            data-pos-search
            data-testid="pos-search-input"
            :value="searchQuery"
            @input="$emit('update:searchQuery', $event.target.value)"
            type="text"
            class="w-full h-11 md:h-12 ps-11 pe-12 lg:pe-24 bg-slate-50 dark:bg-slate-950 border-2 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-4 focus:ring-theme-primary/20 rounded-2xl text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 transition-all focus:outline-none"
            :placeholder="$t('pos.search_placeholder_long')"
            @keydown.down.prevent="$emit('navigate-dropdown', 'down')"
            @keydown.up.prevent="$emit('navigate-dropdown', 'up')"
            @keydown.enter.prevent="$emit('select-highlighted')"
            @keydown.esc="$emit('close-dropdown')"
            @focus="$emit('update:isSearchFocused', true)"
          />

          <div class="absolute inset-y-0 end-0 pe-1 md:pe-2 flex items-center gap-1.5">
            <button
              v-if="searchQuery"
              type="button"
              @click="
                $emit('update:searchQuery', '');
                focusSearch();
              "
              class="w-11 h-11 md:w-9 md:h-9 pointer-coarse:w-11 pointer-coarse:h-11 flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg cursor-pointer"
              :aria-label="$t('common.clear_search')"
            >
              <X class="w-4 h-4" aria-hidden="true" />
            </button>
            <kbd
              class="hidden lg:inline-block pointer-coarse:hidden px-2 py-1 text-xs font-mono font-bold bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-lg border border-slate-300 dark:border-slate-700"
              >F2</kbd
            >
          </div>
        </div>

        <!-- 🌟 FLOATING LIVE SEARCH RESULTS DROPDOWN -->
        <div
          v-if="isSearchFocused && searchResults.length > 0"
          class="absolute top-full start-0 end-0 mt-2 bg-white dark:bg-slate-900 border-2 border-theme-primary/50 rounded-2xl shadow-2xl overflow-hidden z-50 max-h-[60dvh] md:max-h-96 overflow-y-auto custom-scrollbar animate-in fade-in slide-in-from-top-2 duration-150"
        >
          <div
            class="p-2 bg-slate-50 dark:bg-slate-950/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs font-bold text-slate-500"
          >
            <span
              >{{ $t('pos.search_results_count', { count: searchResults.length }) }}
              <span class="pointer-coarse:hidden">• {{ $t('pos.search_nav_hint') }}</span></span
            >
            <span class="text-theme-primary"
              >{{ $t('pos.prices_label') }}:
              {{ activePriceTier === 'wholesale' ? $t('pos.wholesale_short') : $t('pos.retail_short') }}</span
            >
          </div>

          <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
            <button
              v-for="(item, idx) in searchResults"
              :key="item.id"
              type="button"
              @click="$emit('add-item', item)"
              @mouseenter="$emit('update:highlightedIndex', idx)"
              class="w-full min-h-11 p-3.5 text-start transition-all cursor-pointer group space-y-2"
              :class="
                highlightedIndex === idx
                  ? 'bg-theme-light dark:bg-slate-800 text-slate-950 dark:text-white ring-1 ring-inset ring-theme-primary'
                  : 'hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-800 dark:text-slate-200'
              "
            >
              <!-- Top Row: Add Icon + Product Name (multiline break-words) + Retail Price -->
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                  <div
                    class="w-8 h-8 rounded-xl flex items-center justify-center font-mono font-bold text-xs shrink-0 mt-0.5"
                    :class="
                      highlightedIndex === idx
                        ? 'bg-theme-primary text-slate-950 font-black'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-500'
                    "
                  >
                    +
                  </div>
                  <div class="min-w-0 flex-1">
                    <div
                      class="font-black text-xs sm:text-sm text-slate-950 dark:text-white leading-snug break-words"
                      dir="auto"
                    >
                      {{ item.name }}
                    </div>
                    <div v-if="item.category" class="mt-0.5">
                      <span
                        class="text-xs font-normal px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 font-tajawal inline-block"
                      >
                        {{ item.category }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Price Breakdown Top-End -->
                <div class="text-end shrink-0 ps-2">
                  <div class="text-sm sm:text-base font-black font-mono text-emerald-600 dark:text-emerald-400">
                    {{ formatMoney(priceFor(item, activePriceTier)) }}
                    <span class="text-xs font-normal text-slate-400 font-tajawal">{{ currencyLabel }}</span>
                  </div>
                </div>
              </div>

              <!-- Bottom Row: Item Code & Live Stock Badge -->
              <div
                class="flex items-center justify-between text-xs pt-1 border-t border-slate-100/60 dark:border-slate-800/40"
              >
                <div class="flex items-center gap-2 font-mono text-xs text-slate-500">
                  <span class="font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">{{
                    item.code || '—'
                  }}</span>
                  <span>•</span>
                  <span
                    class="font-bold px-1.5 py-0.5 rounded text-xs"
                    :class="
                      item.current_stock > 0
                        ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10'
                        : 'text-rose-500 bg-rose-500/10'
                    "
                  >
                    {{
                      item.current_stock > 0
                        ? $t('pos.available_stock_badge', {
                            qty: formatMoney(item.current_stock),
                            unit: item.unit || '',
                          })
                        : $t('pos.out_of_stock_badge', { qty: formatMoney(item.current_stock), unit: item.unit || '' })
                    }}
                  </span>
                </div>

                <span
                  class="hidden sm:inline-flex text-xs font-bold px-2 py-0.5 rounded-md bg-theme-primary text-slate-950 group-hover:scale-105 transition-transform shadow-2xs items-center gap-1"
                >
                  <span>{{ $t('pos.add_item_btn') }}</span>
                </span>
              </div>
            </button>
          </div>
        </div>

        <!-- 🔄 SEARCHING REMOTE DATABASE SPINNER STATE -->
        <div
          v-if="isSearchFocused && isSearching && searchResults.length === 0"
          class="absolute top-full start-0 end-0 mt-2 bg-white dark:bg-slate-900 border-2 border-theme-primary/40 rounded-2xl shadow-2xl p-4 text-center z-50 animate-in fade-in duration-150"
        >
          <div
            class="flex items-center justify-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 font-tajawal"
          >
            <span class="w-4 h-4 border-2 border-theme-primary border-t-transparent rounded-full animate-spin"></span>
            <span>{{ $t('pos.searching_database') }}</span>
          </div>
        </div>

        <!-- 🚫 NOT FOUND EMPTY STATE -->
        <div
          v-else-if="isSearchFocused && searchQuery.trim().length > 0 && searchResults.length === 0 && !isSearching"
          class="absolute top-full start-0 end-0 mt-2 bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-6 text-center z-50 animate-in fade-in slide-in-from-top-2 duration-150"
        >
          <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center mx-auto mb-2">
            <Search class="w-6 h-6" />
          </div>
          <h3 class="text-sm font-black text-slate-900 dark:text-white font-tajawal">
            {{ $t('pos.no_items_found_search', { query: searchQuery }) }}
          </h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-tajawal">
            {{ $t('pos.no_items_found_search_hint') }}
          </p>
        </div>
      </div>

      <!-- Left: Customer, Catalog Toggle, Tier & Action Buttons -->
      <div class="flex items-center gap-2 shrink-0">
        <!-- Recent sales (F8) -->
        <button
          type="button"
          data-testid="pos-recent-sales-btn"
          class="hidden lg:flex min-h-[44px] min-w-[44px] px-3 py-2 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700/80 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
          :title="`${$t('pos.recent_sales_title')} (F8)`"
          :aria-label="$t('pos.recent_sales_title')"
          @click="$emit('open-recent-sales')"
        >
          <History class="w-4 h-4 text-theme-primary shrink-0" />
          <span class="hidden xl:inline">{{ $t('pos.recent_sales_title') }}</span>
        </button>

        <!-- Customer Selection Button -->
        <button
          type="button"
          data-testid="pos-customer-picker-btn"
          @click="$emit('open-customer-picker')"
          :aria-label="$t('pos.change_customer')"
          class="relative min-h-[44px] min-w-[44px] justify-center md:justify-start px-2 md:px-3 py-2 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700/80 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-2xs"
        >
          <Users class="w-4 h-4 text-theme-primary shrink-0" />
          <span
            v-if="selectedCustomer"
            class="md:hidden absolute top-1.5 end-1.5 w-2 h-2 rounded-full bg-theme-primary"
            aria-hidden="true"
          ></span>
          <div class="hidden sm:block text-start leading-tight min-w-0">
            <div
              data-testid="pos-selected-customer-name"
              class="text-slate-900 dark:text-white truncate max-w-[72px] xl:max-w-[130px] font-black"
            >
              {{ selectedCustomer?.name || $t('pos.general_cash_customer') }}
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 font-mono truncate max-w-[72px] xl:max-w-[130px]">
              {{ selectedCustomer?.phone || $t('pos.general_cash_customer') }}
            </div>
          </div>
        </button>

        <!-- Price Tier Toggle (Retail / Wholesale) -->
        <div
          class="hidden lg:flex p-0.5 bg-slate-200 dark:bg-slate-800 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold"
        >
          <button
            type="button"
            data-testid="pos-tier-retail"
            @click="$emit('update:activePriceTier', 'retail')"
            class="min-h-[36px] pointer-coarse:min-h-11 px-2.5 py-1 rounded-lg transition cursor-pointer"
            :class="
              activePriceTier === 'retail'
                ? 'bg-white dark:bg-slate-900 text-theme-primary font-black shadow-xs'
                : 'text-slate-600 dark:text-slate-400'
            "
          >
            {{ $t('pos.retail_short') }}
          </button>
          <button
            type="button"
            data-testid="pos-tier-wholesale"
            @click="$emit('update:activePriceTier', 'wholesale')"
            class="min-h-[36px] pointer-coarse:min-h-11 px-2.5 py-1 rounded-lg transition cursor-pointer"
            :class="
              activePriceTier === 'wholesale'
                ? 'bg-white dark:bg-slate-900 text-purple-500 font-black shadow-xs'
                : 'text-slate-600 dark:text-slate-400'
            "
          >
            {{ $t('pos.wholesale_short') }}
          </button>
        </div>

        <!-- Clear Cart -->
        <button
          type="button"
          @click="$emit('clear-cart')"
          :disabled="cartEmpty"
          class="hidden lg:flex min-h-[44px] min-w-[44px] p-2.5 bg-slate-100 hover:bg-rose-500/20 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-500/20 dark:hover:text-rose-400 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700 rounded-xl transition disabled:opacity-30 cursor-pointer items-center justify-center"
          :title="$t('pos.clear_cart_full')"
          :aria-label="$t('pos.clear_cart_full')"
        >
          <RotateCcw class="w-4 h-4" />
        </button>

        <!-- Phones and tablets (< 1024px): store, shift, recent sales, price tier and clear cart live in one sheet -->
        <button
          type="button"
          class="lg:hidden relative w-11 h-11 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center cursor-pointer active:scale-95 transition"
          data-testid="pos-phone-menu-btn"
          :aria-label="$t('pos.phone_menu_btn')"
          aria-haspopup="dialog"
          @click="showPhoneMenu = true"
        >
          <EllipsisVertical class="w-5 h-5" aria-hidden="true" />
          <span
            class="absolute top-1.5 end-1.5 w-2.5 h-2.5 rounded-full border-2 border-white dark:border-slate-900"
            :class="activeShift ? 'bg-emerald-500' : 'bg-rose-500'"
            aria-hidden="true"
          ></span>
        </button>
      </div>
    </div>

    <POSPhoneMenuSheet
      :show="showPhoneMenu"
      :stores="stores"
      :active-store="activeStore"
      :active-shift="activeShift"
      :active-price-tier="activePriceTier"
      :cart-empty="cartEmpty"
      @close="showPhoneMenu = false"
      @switch-store="$emit('switch-store', $event)"
      @open-shift-menu="$emit('open-shift-menu')"
      @open-recent-sales="$emit('open-recent-sales')"
      @update:active-price-tier="$emit('update:activePriceTier', $event)"
      @clear-cart="$emit('clear-cart')"
    />
  </header>
</template>

<script setup>
import { ref } from 'vue';
import { ArrowRight, Search, Users, RotateCcw, Zap, ChevronDown, History, X, EllipsisVertical } from 'lucide-vue-next';
import POSPhoneMenuSheet from './POSPhoneMenuSheet.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { priceFor } from '../../helpers/posCartLines';

const { formatMoney, currencyLabel } = useFormatters();
const searchInputRef = ref(null);
const showPhoneMenu = ref(false);

defineProps({
  activeStore: { type: Object, default: null },
  stores: { type: Array, default: () => [] },
  activeShift: { type: Object, default: null },
  searchQuery: { type: String, default: '' },
  isSearchFocused: { type: Boolean, default: false },
  searchResults: { type: Array, default: () => [] },
  highlightedIndex: { type: Number, default: 0 },
  activePriceTier: { type: String, default: 'retail' },
  selectedCustomer: { type: Object, default: null },
  cartEmpty: { type: Boolean, default: true },
  isSearching: { type: Boolean, default: false },
});

defineEmits([
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
  'switch-store',
  'open-recent-sales',
  'open-shift-menu',
]);

const focusSearch = () => searchInputRef.value?.focus();

defineExpose({ focusSearch });
</script>
