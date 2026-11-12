<script setup>
import { computed, ref } from 'vue';
import { Percent, Trash2 } from 'lucide-vue-next';
import POSLineDiscount from './POSLineDiscount.vue';
import POSLinePriceField from './POSLinePriceField.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useUnits } from '../../Composables/useUnits';
import { useCartQtyField } from '../../Composables/POS/useCartQtyField';
import { isPositive } from '../../helpers/decimal';
import { lineGross, lineNet, lineDiscountAmount } from '../../helpers/posDiscount';
import { focusLineField } from '../../helpers/posFocus';

const props = defineProps({
  line: { type: Object, required: true },
  index: { type: Number, required: true },
});

const emit = defineEmits([
  'increase-qty',
  'decrease-qty',
  'update-qty',
  'update-price',
  'update-discount',
  'remove-item',
]);

const { formatMoney } = useFormatters();
const { unitLabel } = useUnits();
const { onNumberInput, onFieldBlur, onFieldFocus, revertField } = useCartQtyField({
  emit,
  getLine: () => props.line,
});

const hasDiscount = computed(() => isPositive(lineDiscountAmount(props.line)));
const showDiscount = ref(false);

const stepClass =
  'w-9 h-9 pointer-coarse:w-11 pointer-coarse:h-11 shrink-0 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black text-base transition active:scale-95 cursor-pointer';
</script>

<template>
  <div
    :data-line-id="line.id"
    data-testid="pos-cart-row"
    class="px-1 py-0.5 border-b border-slate-100 dark:border-slate-800/70 last:border-b-0"
  >
    <div class="flex items-center gap-2 h-[18px] min-w-0">
      <span class="min-w-0 flex-1 truncate font-black text-sm leading-[18px] text-slate-950 dark:text-white" dir="auto">
        {{ line.name }}
      </span>
      <span
        v-if="line.is_weighted"
        class="shrink-0 text-[11px] font-bold text-slate-500 dark:text-slate-400"
        data-testid="pos-cart-unit"
        >{{ unitLabel(line.unit) }}</span
      >
      <span
        v-if="hasDiscount"
        class="shrink-0 text-[11px] font-mono text-slate-400 line-through"
        data-testid="pos-line-gross"
        >{{ formatMoney(lineGross(line)) }}</span
      >
      <span class="shrink-0 font-mono font-black text-sm text-emerald-600 dark:text-emerald-400">{{
        formatMoney(lineNet(line))
      }}</span>
    </div>

    <div class="flex items-center gap-1 min-w-0">
      <button
        type="button"
        tabindex="-1"
        :class="stepClass"
        :aria-label="$t('pos.decrease_qty')"
        @click="emit('decrease-qty', index)"
      >
        -
      </button>
      <input
        type="text"
        inputmode="decimal"
        :value="line.quantity"
        data-testid="pos-cart-qty"
        :data-line-id="line.id"
        data-line-field="qty"
        :aria-label="$t('pos.item_qty')"
        class="w-12 h-9 pointer-coarse:h-11 shrink-0 text-center font-mono font-black text-sm bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
        @focus="onFieldFocus"
        @blur="onFieldBlur($event, index, 'quantity')"
        @input="onNumberInput($event, index, 'update-qty')"
        @keydown.enter.prevent="focusLineField(line.id, 'price')"
        @keydown.esc.prevent="revertField($event, index, 'update-qty')"
      />
      <button
        type="button"
        tabindex="-1"
        :class="stepClass"
        :aria-label="$t('pos.increase_qty')"
        @click="emit('increase-qty', index)"
      >
        +
      </button>

      <div class="min-w-0 flex-1 flex justify-end">
        <POSLinePriceField fluid :line="line" :index="index" @update-price="emit('update-price', $event)" />
      </div>

      <button
        type="button"
        tabindex="-1"
        data-testid="pos-line-discount-toggle"
        data-line-discount-toggle
        :data-line-id="line.id"
        :aria-expanded="showDiscount"
        :aria-label="$t('pos.line_discount_label')"
        :title="$t('pos.line_discount_label')"
        class="w-9 h-9 pointer-coarse:w-11 pointer-coarse:h-11 shrink-0 rounded-lg border flex items-center justify-center transition active:scale-95 cursor-pointer"
        :class="
          hasDiscount
            ? 'bg-theme-primary text-slate-950 border-theme-primary'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'
        "
        @click="showDiscount = !showDiscount"
      >
        <Percent class="w-4 h-4" aria-hidden="true" />
      </button>
      <button
        type="button"
        tabindex="-1"
        class="w-9 h-9 pointer-coarse:w-11 pointer-coarse:h-11 shrink-0 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 flex items-center justify-center transition cursor-pointer"
        :title="$t('pos.delete_item')"
        :aria-label="$t('pos.delete_item')"
        @click="emit('remove-item', index)"
      >
        <Trash2 class="w-4 h-4" aria-hidden="true" />
      </button>
    </div>

    <div v-if="showDiscount" class="flex items-center justify-between gap-2 pt-1">
      <span class="text-[11px] font-bold text-slate-500">{{ $t('pos.line_discount_label') }}</span>
      <POSLineDiscount :line="line" :index="index" @update-discount="emit('update-discount', $event)" />
    </div>
  </div>
</template>
