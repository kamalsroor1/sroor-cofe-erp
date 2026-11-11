<script setup>
import { computed } from 'vue';
import { sanitizeAmountInput } from '../../helpers/posCheckout';
import { focusPosSearch } from '../../helpers/posFocus';
import { DISCOUNT_FIXED, DISCOUNT_PERCENT } from '../../helpers/posDiscount';
import { useFormatters } from '../../Composables/useFormatters';

const { currencyLabel } = useFormatters();

const props = defineProps({
  line: { type: Object, required: true },
  index: { type: Number, required: true },
});

const emit = defineEmits(['update-discount']);

const isFixed = computed(() => props.line.discount_type === DISCOUNT_FIXED);
const value = computed(() => {
  const raw = props.line.discount_value ?? '';
  return parseFloat(raw) > 0 ? raw : '';
});

const toggleMode = () =>
  emit('update-discount', { index: props.index, type: isFixed.value ? DISCOUNT_PERCENT : DISCOUNT_FIXED });

const onInput = (event) => {
  const cleaned = sanitizeAmountInput(event.target.value);
  if (cleaned !== event.target.value) event.target.value = cleaned;
  emit('update-discount', { index: props.index, value: cleaned });
};

// Esc drops the line discount typed so far and returns to the search box.
const onEscape = (event) => {
  event.target.value = '';
  emit('update-discount', { index: props.index, value: '' });
  focusPosSearch();
};
</script>

<template>
  <div class="flex items-center gap-1" data-testid="pos-line-discount">
    <button
      type="button"
      tabindex="-1"
      data-testid="pos-line-discount-mode"
      :aria-label="isFixed ? $t('pos.discount_mode_amount') : $t('pos.discount_mode_percent')"
      :title="isFixed ? $t('pos.discount_mode_amount') : $t('pos.discount_mode_percent')"
      class="min-h-11 min-w-11 px-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-black text-slate-700 dark:text-slate-200 transition active:scale-95 cursor-pointer"
      @click="toggleMode"
    >
      {{ isFixed ? currencyLabel : '%' }}
    </button>
    <input
      type="text"
      inputmode="decimal"
      :value="value"
      :placeholder="$t('pos.line_discount_placeholder')"
      :aria-label="$t('pos.line_discount_label')"
      :data-line-id="line.id"
      data-line-field="discount"
      data-testid="pos-line-discount-input"
      class="w-20 min-h-11 px-1.5 text-center font-mono font-black text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
      @focus="$event.target.select()"
      @input="onInput"
      @keydown.enter.prevent="focusPosSearch()"
      @keydown.esc.prevent="onEscape"
    />
  </div>
</template>
