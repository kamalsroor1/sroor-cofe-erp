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
        <!-- 📱 Mobile Cart Items Card List (block md:hidden) -->
        <div class="block md:hidden space-y-2 p-2 divide-y divide-slate-100 dark:divide-slate-800/60">
          <div
            v-for="(item, idx) in cart"
            :key="item.id"
            :data-line-id="item.id"
            class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3"
          >
            <!-- Row 1: Header (#, Name, Delete) -->
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-start gap-2 min-w-0 flex-1">
                <span
                  class="w-6 h-6 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono font-black text-xs flex items-center justify-center shrink-0 mt-0.5"
                >
                  {{ idx + 1 }}
                </span>
                <div class="min-w-0 flex-1">
                  <div
                    class="font-black text-xs sm:text-sm text-slate-950 dark:text-white leading-snug break-words"
                    dir="auto"
                  >
                    {{ item.name }}
                  </div>
                  <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 font-mono">
                    <span
                      class="font-bold text-slate-400 bg-white dark:bg-slate-900 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800"
                      >{{ item.code || '—' }}</span
                    >
                    <span>•</span>
                    <span class="text-slate-500 font-tajawal">{{ unitLabel(item.unit) }}</span>
                  </div>
                </div>
              </div>

              <button
                type="button"
                tabindex="-1"
                @click="$emit('remove-item', idx)"
                class="min-h-11 min-w-11 p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer flex items-center justify-center shrink-0"
                :title="$t('pos.delete_item')"
                :aria-label="$t('pos.delete_item')"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <!-- Row 2: Quantity Controls + Price & Subtotal -->
            <div
              class="flex flex-col sm:flex-row sm:items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-800/60 gap-2"
            >
              <!-- Qty buttons -->
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  tabindex="-1"
                  @click="$emit('decrease-qty', idx)"
                  class="w-11 h-11 rounded-xl bg-white dark:bg-slate-800 hover:bg-rose-500/20 hover:text-rose-500 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700 text-base"
                >
                  -
                </button>
                <input
                  type="text"
                  inputmode="decimal"
                  :value="item.quantity"
                  data-testid="pos-cart-qty"
                  :data-line-id="item.id"
                  data-line-field="qty"
                  @focus="onFieldFocus"
                  @blur="onFieldBlur($event, idx, 'quantity')"
                  @input="onNumberInput($event, idx, 'update-qty')"
                  @keydown.enter.prevent="focusLineField(item.id, 'price')"
                  @keydown.esc.prevent="revertField($event, idx, 'update-qty')"
                  class="w-16 h-11 text-center font-mono font-black text-sm bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                />
                <button
                  type="button"
                  tabindex="-1"
                  @click="$emit('increase-qty', idx)"
                  class="w-11 h-11 rounded-xl bg-white dark:bg-slate-800 hover:bg-emerald-500/20 hover:text-emerald-500 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700 text-base"
                >
                  +
                </button>
                <span
                  v-if="item.is_weighted"
                  class="ms-1 text-[11px] font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap"
                  data-testid="pos-cart-unit"
                  >{{ unitLabel(item.unit) }}</span
                >
              </div>

              <!-- Price Breakdown (Editable Selling Price) -->
              <div class="flex items-center justify-between sm:justify-end gap-2">
                <div class="flex items-center gap-1">
                  <label class="text-[10px] font-bold text-slate-500">{{ $t('pos.item_price') }}:</label>
                  <POSLinePriceField compact :line="item" :index="idx" @update-price="$emit('update-price', $event)" />
                </div>
                <div class="text-xs font-black font-mono text-emerald-600 dark:text-emerald-400">
                  <div
                    v-if="hasLineDiscount(item)"
                    class="text-[10px] font-normal text-slate-400 line-through"
                    data-testid="pos-line-gross"
                  >
                    {{ formatMoney(lineGross(item)) }}
                  </div>
                  {{ formatMoney(lineNet(item)) }}
                  <span class="text-[9px] font-normal text-slate-400 font-tajawal">{{ currencyLabel }}</span>
                </div>
              </div>
            </div>

            <!-- Line discount (POS-07) -->
            <div class="flex items-center justify-between gap-2">
              <span class="text-[11px] font-bold text-slate-500">{{ $t('pos.line_discount_label') }}</span>
              <POSLineDiscount :line="item" :index="idx" @update-discount="$emit('update-discount', $event)" />
            </div>
          </div>
        </div>

        <!-- Tablet / desktop: compact two-line rows (name + total, then qty / price / discount / delete) -->
        <div class="hidden md:block px-2">
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
import { ShoppingCart, Trash2 } from 'lucide-vue-next';
import POSCartRow from './POSCartRow.vue';
import POSLineDiscount from './POSLineDiscount.vue';
import POSLinePriceField from './POSLinePriceField.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useUnits } from '../../Composables/useUnits';
import { useCartQtyField } from '../../Composables/POS/useCartQtyField';
import { isPositive } from '../../helpers/decimal';
import { lineGross, lineNet, lineDiscountAmount } from '../../helpers/posDiscount';
import { focusLineField } from '../../helpers/posFocus';

const { formatMoney, currencyLabel } = useFormatters();
const { unitLabel } = useUnits();

const props = defineProps({
  cart: { type: Array, default: () => [] },
  totalQty: { type: Number, default: 0 },
});

const emit = defineEmits([
  'increase-qty',
  'decrease-qty',
  'update-qty',
  'update-price',
  'update-discount',
  'remove-item',
]);

const hasLineDiscount = (line) => isPositive(lineDiscountAmount(line));

const { onNumberInput, onFieldBlur, onFieldFocus, revertField } = useCartQtyField({
  emit,
  getLine: (index) => props.cart[index],
});
</script>
