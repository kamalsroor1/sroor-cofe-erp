<script setup>
import { ShoppingBag } from 'lucide-vue-next';
import PosChipListField from './PosChipListField.vue';
import { MAX_DENOMINATIONS, MAX_PRESETS, SALE_PAYMENT_METHODS } from '../../../helpers/posSaleSettingsForm';

const props = defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
});

const emit = defineEmits(['update:field']);

const firstError = (field) => {
  // The API reports a bad list entry under `<field>.<index>`.
  const key = Object.keys(props.errors || {}).find((k) => k === field || k.startsWith(`${field}.`));
  const err = key ? props.errors[key] : null;
  if (!err) return null;
  return Array.isArray(err) ? err[0] : err;
};

// Same labels the POS checkout panel uses.
const methodLabelKey = {
  cash: 'pos.method_cash',
  instapay: 'pos.instapay',
  e_wallet: 'pos.smart_wallet',
  visa: 'pos.visa_card',
  bank_transfer: 'pos.bank_transfer',
};

const toggleMethod = (key) => {
  if (key === 'cash' || !props.canManage) return;
  const current = props.form.pos_enabled_payment_methods || [];
  const next = current.includes(key) ? current.filter((m) => m !== key) : [...current, key];
  emit(
    'update:field',
    'pos_enabled_payment_methods',
    SALE_PAYMENT_METHODS.filter((m) => m === 'cash' || next.includes(m))
  );
};

const choiceClass = (active) =>
  active
    ? 'bg-theme-light border-theme-primary text-theme-primary'
    : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300';
</script>

<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-6 font-tajawal transition-colors"
    data-testid="settings-pos-sale"
  >
    <div class="flex items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
      <div
        class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center shrink-0"
      >
        <ShoppingBag class="w-5 h-5" aria-hidden="true" />
      </div>
      <div class="min-w-0">
        <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.sale_settings_title') }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.sale_settings_sub') }}</p>
      </div>
    </div>

    <div class="space-y-2" data-testid="setting-pos_enabled_payment_methods">
      <span class="block text-sm font-black text-slate-800 dark:text-slate-200">{{
        $t('settings.pos_enabled_payment_methods')
      }}</span>
      <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.pos_enabled_payment_methods_desc') }}</p>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="key in SALE_PAYMENT_METHODS"
          :key="key"
          type="button"
          role="switch"
          :aria-checked="(form.pos_enabled_payment_methods || []).includes(key)"
          :disabled="!canManage || key === 'cash'"
          :data-testid="`pos-method-${key}`"
          class="min-h-[44px] px-4 rounded-xl border font-black text-sm cursor-pointer disabled:cursor-not-allowed disabled:opacity-70"
          :class="choiceClass((form.pos_enabled_payment_methods || []).includes(key))"
          @click="toggleMethod(key)"
        >
          {{ $t(methodLabelKey[key]) }}
        </button>
      </div>
      <p v-if="firstError('pos_enabled_payment_methods')" class="text-xs font-bold text-rose-600" role="alert">
        {{ firstError('pos_enabled_payment_methods') }}
      </p>
    </div>

    <PosChipListField
      id="pos_cash_denominations"
      :label="$t('settings.pos_cash_denominations')"
      :hint="$t('settings.pos_cash_denominations_desc')"
      :model-value="form.pos_cash_denominations || []"
      :max="MAX_DENOMINATIONS"
      :min="1"
      :disabled="!canManage"
      :error="firstError('pos_cash_denominations')"
      @update:model-value="emit('update:field', 'pos_cash_denominations', $event)"
    />

    <PosChipListField
      id="pos_discount_presets"
      :label="$t('settings.pos_discount_presets')"
      :hint="$t('settings.pos_discount_presets_desc')"
      :model-value="form.pos_discount_presets || []"
      :max="MAX_PRESETS"
      :min="0"
      :max-value="100"
      suffix="%"
      :disabled="!canManage"
      :error="firstError('pos_discount_presets')"
      @update:model-value="emit('update:field', 'pos_discount_presets', $event)"
    />

    <div class="space-y-2" data-testid="setting-pos_auto_print">
      <span class="block text-sm font-black text-slate-800 dark:text-slate-200">{{ $t('settings.pos_auto_print') }}</span>
      <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.pos_auto_print_desc') }}</p>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="mode in ['none', 'thermal']"
          :key="mode"
          type="button"
          role="radio"
          :aria-checked="form.pos_auto_print === mode"
          :disabled="!canManage"
          :data-testid="`pos-auto-print-${mode}`"
          class="min-h-[44px] px-4 rounded-xl border font-black text-sm cursor-pointer"
          :class="choiceClass(form.pos_auto_print === mode)"
          @click="emit('update:field', 'pos_auto_print', mode)"
        >
          {{ $t(`settings.pos_auto_print_${mode}`) }}
        </button>
      </div>
    </div>

    <div class="space-y-2" data-testid="setting-pos_receipt_copies">
      <span class="block text-sm font-black text-slate-800 dark:text-slate-200">{{ $t('settings.pos_receipt_copies') }}</span>
      <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.pos_receipt_copies_desc') }}</p>
      <div class="flex gap-2">
        <button
          v-for="n in [1, 2, 3]"
          :key="n"
          type="button"
          role="radio"
          :aria-checked="Number(form.pos_receipt_copies) === n"
          :disabled="!canManage"
          :data-testid="`pos-copies-${n}`"
          class="min-w-[44px] min-h-[44px] px-4 rounded-xl border font-mono font-black cursor-pointer"
          :class="choiceClass(Number(form.pos_receipt_copies) === n)"
          @click="emit('update:field', 'pos_receipt_copies', n)"
        >
          {{ n }}
        </button>
      </div>
      <p v-if="firstError('pos_receipt_copies')" class="text-xs font-bold text-rose-600" role="alert">
        {{ firstError('pos_receipt_copies') }}
      </p>
    </div>
  </div>
</template>
