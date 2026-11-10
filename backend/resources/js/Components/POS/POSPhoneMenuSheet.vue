<script setup>
import { computed } from 'vue';
import { History, RotateCcw, Store, Timer } from 'lucide-vue-next';
import BottomSheet from '../Common/BottomSheet.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  stores: { type: Array, default: () => [] },
  activeStore: { type: Object, default: null },
  activeShift: { type: Object, default: null },
  activePriceTier: { type: String, default: 'retail' },
  cartEmpty: { type: Boolean, default: true },
});

const emit = defineEmits([
  'close',
  'switch-store',
  'open-shift-menu',
  'open-recent-sales',
  'update:activePriceTier',
  'clear-cart',
]);

const hasManyStores = computed(() => props.stores.length > 1);

// Actions that open another layer close this sheet first so the sheets never stack on each other.
const run = (event) => {
  emit('close');
  emit(event);
};

const tierClass = (tier, activeClass) =>
  props.activePriceTier === tier
    ? `bg-white dark:bg-slate-900 font-black shadow-xs ${activeClass}`
    : 'text-slate-600 dark:text-slate-400';

const rowClass =
  'w-full min-h-12 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-3 cursor-pointer active:scale-[0.98] transition text-start';
</script>

<template>
  <BottomSheet :show="show" :title="$t('pos.phone_menu_title')" test-id="pos-phone-menu" @close="emit('close')">
    <div class="p-4 space-y-3">
      <!-- Branch -->
      <div class="space-y-1">
        <label for="pos-menu-store" class="text-xs font-bold text-slate-500 dark:text-slate-400">
          {{ $t('pos.phone_store_label') }}
        </label>
        <div
          class="flex items-center gap-3 min-h-12 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800"
        >
          <Store class="w-4 h-4 text-theme-primary shrink-0" aria-hidden="true" />
          <select
            v-if="hasManyStores"
            id="pos-menu-store"
            :value="activeStore?.id"
            class="flex-1 min-w-0 min-h-12 bg-transparent text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none cursor-pointer"
            @change="emit('switch-store', $event.target.value)"
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
          <span v-else id="pos-menu-store" class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">
            {{ activeStore?.name || $t('common.main_branch') }}
          </span>
        </div>
      </div>

      <!-- Shift -->
      <button type="button" data-testid="pos-menu-shift" :class="rowClass" @click="run('open-shift-menu')">
        <Timer
          class="w-4 h-4 shrink-0"
          :class="activeShift ? 'text-emerald-500' : 'text-rose-500'"
          aria-hidden="true"
        />
        <span :class="activeShift ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'">
          <template v-if="activeShift">
            {{ $t('pos.shift_number_badge', { number: activeShift.shift_number || activeShift.id }) }}
          </template>
          <template v-else>{{ $t('pos.no_shift_open') }}</template>
        </span>
      </button>

      <!-- Recent sales -->
      <button type="button" data-testid="pos-menu-recent-sales" :class="rowClass" @click="run('open-recent-sales')">
        <History class="w-4 h-4 text-theme-primary shrink-0" aria-hidden="true" />
        <span>{{ $t('pos.recent_sales_title') }}</span>
      </button>

      <!-- Price tier -->
      <div class="space-y-1">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('pos.prices_label') }}</span>
        <div
          class="grid grid-cols-2 p-0.5 bg-slate-200 dark:bg-slate-800 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-bold"
          role="group"
          :aria-label="$t('pos.prices_label')"
        >
          <button
            type="button"
            data-testid="pos-menu-tier-retail"
            :aria-pressed="activePriceTier === 'retail'"
            class="min-h-12 rounded-lg transition cursor-pointer"
            :class="tierClass('retail', 'text-theme-primary')"
            @click="emit('update:activePriceTier', 'retail')"
          >
            {{ $t('pos.retail_short') }}
          </button>
          <button
            type="button"
            data-testid="pos-menu-tier-wholesale"
            :aria-pressed="activePriceTier === 'wholesale'"
            class="min-h-12 rounded-lg transition cursor-pointer"
            :class="tierClass('wholesale', 'text-purple-500')"
            @click="emit('update:activePriceTier', 'wholesale')"
          >
            {{ $t('pos.wholesale_short') }}
          </button>
        </div>
      </div>

      <!-- Clear cart (separated from the other rows: it is the destructive one) -->
      <button
        type="button"
        data-testid="pos-menu-clear-cart"
        :disabled="cartEmpty"
        class="w-full min-h-12 mt-2 px-3 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400 text-sm font-black flex items-center justify-center gap-2 cursor-pointer disabled:opacity-30 active:scale-[0.98] transition"
        @click="run('clear-cart')"
      >
        <RotateCcw class="w-4 h-4" aria-hidden="true" />
        <span>{{ $t('pos.clear_cart_full') }}</span>
      </button>
    </div>
  </BottomSheet>
</template>
