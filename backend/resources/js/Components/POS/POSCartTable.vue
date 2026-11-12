<template>
  <div class="flex flex-col h-full min-h-0 font-tajawal select-none">
    <!-- Table Header Slot (Multi-Cart Order Tabs) -->
    <div v-if="$slots.header" class="shrink-0">
      <slot name="header" />
    </div>

    <!-- ACTIVE INVOICE ITEMS CONTAINER -->
    <div
      data-testid="pos-cart-list"
      class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto my-1 custom-scrollbar rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 shadow-xs"
    >
      <template v-if="cart.length > 0">
        <!-- Compact two-line rows (name + total, then qty / price / discount / delete), shared by the desktop column and the phone/tablet sheet -->
        <div class="px-2">
          <POSCartRow
            v-for="(item, idx) in cart"
            :key="item.id"
            :line="item"
            :index="idx"
            @increase-qty="$emit('increase-qty', $event)"
            @decrease-qty="$emit('decrease-qty', $event)"
            @update-qty="$emit('update-qty', $event)"
            @update-price="$emit('update-price', $event)"
            @update-discount="$emit('update-discount', $event)"
            @remove-item="$emit('remove-item', $event)"
          />
        </div>
      </template>

      <!-- Empty Cart State -->
      <div v-else class="h-full min-h-[260px] flex flex-col items-center justify-center p-8 text-center text-slate-400">
        <div
          class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl mb-3 shadow-inner"
        >
          <ShoppingCart class="w-8 h-8" aria-hidden="true" />
        </div>
        <h3 class="text-base font-black text-slate-800 dark:text-slate-200">{{ $t('pos.empty_cart_title') }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm">
          {{ $t('pos.empty_cart_desc') }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ShoppingCart } from 'lucide-vue-next';
import POSCartRow from './POSCartRow.vue';

defineProps({
  cart: { type: Array, default: () => [] },
  totalQty: { type: Number, default: 0 },
});

defineEmits(['increase-qty', 'decrease-qty', 'update-qty', 'update-price', 'update-discount', 'remove-item']);
</script>
