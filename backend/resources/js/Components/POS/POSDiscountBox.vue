<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';
import { sanitizeAmountInput } from '../../helpers/posCheckout';
import { dRoundTo, isPositive } from '../../helpers/decimal';
import { focusPosSearch } from '../../helpers/posFocus';
import { DISCOUNT_FIXED, DISCOUNT_PERCENT } from '../../helpers/posDiscount';

const props = defineProps({
  discountValue: { type: [Number, String], default: 0 },
  discountType: { type: String, default: 'percentage' },
  discountSummary: { type: Object, default: null },
  maxDiscountPercent: { type: String, default: null },
  canOverrideDiscount: { type: Boolean, default: false },
  // false keeps only the cap warning on screen (the collapsed discount line of the compact checkout footer).
  showControls: { type: Boolean, default: true },
});

const emit = defineEmits(['apply-discount']);

const { currencyLabel } = useFormatters();
const { t } = useTrans();

// POS-07: percent or amount; the presets are the quick percents (0 clears the discount).
const discountPresets = [0, 5, 10];
const discountModes = computed(() => [
  { key: DISCOUNT_PERCENT, symbol: '%', label: t('pos.discount_mode_percent') },
  { key: DISCOUNT_FIXED, symbol: currencyLabel.value, label: t('pos.discount_mode_amount') },
]);
const discountInputValue = computed(() =>
  isPositive(String(props.discountValue ?? '0')) ? String(props.discountValue) : ''
);
const isPresetActive = (preset) =>
  props.discountType === DISCOUNT_PERCENT && Number(props.discountValue || 0) === preset;

const capExceeded = computed(() => !!props.discountSummary?.exceedsCap);
const discountPercentText = computed(() => String(Number(dRoundTo(props.discountSummary?.percentOfGross ?? '0', 2))));
const maxDiscountText = computed(() => String(Number(props.maxDiscountPercent ?? 0)));

const setDiscountMode = (type) => {
  const keep = String(props.discountValue ?? '0');
  const value = type === DISCOUNT_PERCENT && Number(keep) > 100 ? '100' : keep;
  emit('apply-discount', { value, type });
};

const onDiscountInput = (event) => {
  let clean = sanitizeAmountInput(event.target.value);
  if (props.discountType === DISCOUNT_PERCENT && Number(clean) > 100) clean = '100';
  if (clean !== event.target.value) event.target.value = clean;
  emit('apply-discount', { value: clean, type: props.discountType });
};
</script>

<template>
  <div class="space-y-1.5">
    <template v-if="showControls">
      <div class="flex items-center gap-1.5">
        <div
          role="group"
          :aria-label="$t('pos.discount_mode_label')"
          class="flex shrink-0 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700"
        >
          <button
            v-for="mode in discountModes"
            :key="mode.key"
            type="button"
            :data-testid="`pos-discount-mode-${mode.key}`"
            :aria-pressed="discountType === mode.key"
            :aria-label="mode.label"
            :title="mode.label"
            class="min-h-11 min-w-11 px-2 text-xs font-black transition active:scale-95 cursor-pointer"
            :class="
              discountType === mode.key
                ? 'bg-theme-primary text-slate-950'
                : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400'
            "
            @click="setDiscountMode(mode.key)"
          >
            {{ mode.symbol }}
          </button>
        </div>
        <input
          type="text"
          inputmode="decimal"
          data-pos-discount-input
          data-testid="pos-discount-input"
          :value="discountInputValue"
          :placeholder="$t('pos.discount_placeholder')"
          :aria-label="$t('pos.discount_input_label')"
          class="flex-1 min-w-0 min-h-11 px-2 text-center font-mono font-black text-sm bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          @focus="$event.target.select()"
          @input="onDiscountInput"
          @keydown.enter.prevent="focusPosSearch()"
          @keydown.esc.prevent="focusPosSearch()"
        />
      </div>

      <div class="grid grid-cols-3 gap-1.5">
        <button
          v-for="preset in discountPresets"
          :key="preset"
          type="button"
          :data-testid="`pos-discount-preset-${preset}`"
          class="min-h-11 rounded-xl text-xs font-mono font-bold border transition active:scale-95 cursor-pointer"
          :class="
            isPresetActive(preset)
              ? 'bg-theme-primary text-slate-950 font-black border-theme-primary'
              : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800'
          "
          @click="emit('apply-discount', { value: preset, type: 'percentage' })"
        >
          {{ preset }}%
        </button>
      </div>
    </template>

    <p
      v-if="capExceeded"
      role="status"
      data-testid="pos-discount-cap-warning"
      class="px-2 py-1.5 rounded-lg text-xs font-bold text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60"
    >
      {{ $t('pos.discount_cap_warning', { percent: discountPercentText, max: maxDiscountText }) }}
      {{ canOverrideDiscount ? $t('pos.discount_cap_can_override') : $t('pos.discount_cap_needs_approval') }}
    </p>
  </div>
</template>
