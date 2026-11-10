<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Scale } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useUnits } from '../../Composables/useUnits';
import { formatDecimal, isPositive, normalize } from '../../helpers/decimal';
import {
  WEIGHT_PRESETS,
  limitQuantityText,
  qtyFromAmount,
  lineTotal,
  sanitizeDecimalInput,
} from '../../helpers/posWeight';

const props = defineProps({
  show: { type: Boolean, default: false },
  item: { type: Object, default: null },
  unitPrice: { type: [String, Number], default: 0 },
});

const emit = defineEmits(['close', 'confirm']);

const { formatMoney } = useFormatters();
const { unitLabel } = useUnits();

const mode = ref('weight');
const text = ref('');
const inputRef = ref(null);

const unit = computed(() => unitLabel(props.item?.unit));
const hasPrice = computed(() => isPositive(props.unitPrice));

const quantity = computed(() => {
  if (mode.value === 'amount') return qtyFromAmount(text.value, props.unitPrice);
  return normalize(text.value);
});
const total = computed(() => lineTotal(quantity.value, props.unitPrice));
const canConfirm = computed(() => isPositive(quantity.value));

const focusInput = () => nextTick(() => inputRef.value?.focus());

const setMode = (value) => {
  mode.value = value;
  text.value = '';
  focusInput();
};

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    mode.value = 'weight';
    text.value = '';
    focusInput();
  }
);

const onInput = (event) => {
  const next =
    mode.value === 'amount' ? sanitizeDecimalInput(event.target.value) : limitQuantityText(event.target.value);
  text.value = next;
  event.target.value = next;
};

const confirm = (qty) => {
  if (!isPositive(qty)) return;
  emit('confirm', normalize(qty));
};
</script>

<template>
  <AppModal
    :show="show"
    :title="item?.name || ''"
    :subtitle="$t('pos.weight_picker_title')"
    :icon="Scale"
    max-width="sm"
    @close="emit('close')"
  >
    <div class="p-4 space-y-4 font-tajawal select-none" data-testid="pos-weight-picker">
      <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">
        {{ $t('pos.weight_picker_unit_price', { unit }) }}: {{ formatMoney(unitPrice) }} {{ $t('common.currency') }}
      </p>

      <div class="grid grid-cols-4 gap-2">
        <button
          v-for="preset in WEIGHT_PRESETS"
          :key="preset.key"
          type="button"
          :data-testid="`pos-weight-preset-${preset.key}`"
          class="min-h-[56px] rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-black text-base flex flex-col items-center justify-center transition active:scale-95 cursor-pointer hover:border-[var(--color-primary)]"
          @click="confirm(preset.qty)"
        >
          <span>{{ $t(preset.labelKey) }}</span>
          <span class="text-[11px] font-mono font-bold text-slate-500 dark:text-slate-400">
            {{ formatMoney(lineTotal(preset.qty, unitPrice)) }}
          </span>
        </button>
      </div>

      <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800">
        <button
          type="button"
          class="min-h-[44px] rounded-lg text-sm font-black transition cursor-pointer"
          :class="
            mode === 'weight'
              ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
              : 'text-slate-500 dark:text-slate-400'
          "
          @click="setMode('weight')"
        >
          {{ $t('pos.weight_mode_weight') }}
        </button>
        <button
          type="button"
          class="min-h-[44px] rounded-lg text-sm font-black transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
          :class="
            mode === 'amount'
              ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
              : 'text-slate-500 dark:text-slate-400'
          "
          :disabled="!hasPrice"
          :title="hasPrice ? '' : $t('pos.weight_amount_no_price')"
          data-testid="pos-weight-mode-amount"
          @click="setMode('amount')"
        >
          {{ $t('pos.weight_mode_amount') }}
        </button>
      </div>

      <div class="space-y-1.5">
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400" for="pos-weight-input">
          {{
            mode === 'amount'
              ? $t('pos.weight_amount_label', { currency: $t('common.currency') })
              : $t('pos.weight_input_label', { unit })
          }}
        </label>
        <div class="flex gap-2">
          <input
            id="pos-weight-input"
            ref="inputRef"
            :value="text"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            data-testid="pos-weight-input"
            class="flex-1 min-w-0 h-12 px-3 text-center rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-950 dark:text-white text-lg font-mono font-black focus:ring-2 focus:ring-[var(--color-primary)] focus:outline-none"
            @input="onInput"
            @keydown.enter.prevent="confirm(quantity)"
          />
          <button
            type="button"
            :disabled="!canConfirm"
            data-testid="pos-weight-confirm"
            class="min-h-[48px] px-5 rounded-xl btn-primary-theme font-black text-sm transition active:scale-95 cursor-pointer shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
            @click="confirm(quantity)"
          >
            {{ $t('pos.weight_add_to_cart') }}
          </button>
        </div>
      </div>

      <div
        class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700"
      >
        <div class="text-start">
          <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
            {{ $t('pos.weight_resulting_qty') }}
          </div>
          <div class="text-base font-black font-mono text-slate-900 dark:text-white" data-testid="pos-weight-qty">
            {{ formatDecimal(quantity, 3) }} {{ unit }}
          </div>
        </div>
        <div class="text-end">
          <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
            {{ $t('pos.weight_resulting_total') }}
          </div>
          <div class="text-base font-black font-mono text-slate-900 dark:text-white">
            {{ formatMoney(total) }} {{ $t('common.currency') }}
          </div>
        </div>
      </div>

      <p v-if="text && !canConfirm" class="text-xs font-bold text-rose-500">{{ $t('pos.weight_invalid') }}</p>
    </div>
  </AppModal>
</template>
