<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-6 font-tajawal transition-colors"
  >
    <!-- Section Header -->
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center shrink-0"
        >
          <TrendingDown class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">
            {{ $t('settings.inventory_threshold_title') }}
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $t('settings.inventory_threshold_sub') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Threshold Input Row -->
    <div class="max-w-md space-y-2">
      <div class="flex items-center justify-between">
        <label
          for="setting-low_stock_default_threshold"
          class="block text-xs font-black text-slate-700 dark:text-slate-200"
        >
          {{ $t('settings.low_stock_default_threshold') }}
          <span class="text-rose-500 mr-0.5">*</span>
        </label>

        <div class="flex items-center gap-2">
          <span
            v-if="isThresholdModified"
            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
          >
            {{ $t('settings.modified_from_default') }}
          </span>
          <button
            v-if="isThresholdModified && canManage"
            type="button"
            @click="$emit('reset-field', 'low_stock_default_threshold')"
            class="text-[11px] font-bold text-theme-primary hover:underline cursor-pointer flex items-center gap-1"
          >
            <RotateCcw class="w-3 h-3" />
            <span>{{ $t('settings.reset_to_default') }}</span>
          </button>
        </div>
      </div>

      <div class="relative flex items-center">
        <input
          id="setting-low_stock_default_threshold"
          type="text"
          inputmode="decimal"
          :value="form.low_stock_default_threshold ?? '5.000'"
          @input="$emit('update:field', 'low_stock_default_threshold', $event.target.value)"
          @blur="handleBlur"
          :disabled="!canManage"
          :placeholder="$t('settings.threshold_placeholder')"
          class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 outline-hidden transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed font-mono text-center"
          :class="{
            'border-rose-500 dark:border-rose-500/80 focus:border-rose-500': fieldError('low_stock_default_threshold'),
          }"
        />
      </div>

      <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-start gap-1">
        <Info class="w-3.5 h-3.5 shrink-0 mt-0.5 text-slate-400 dark:text-slate-500" />
        <span>{{ $t('settings.inventory_threshold_hint') }}</span>
      </p>

      <p
        v-if="fieldError('low_stock_default_threshold')"
        class="text-xs font-bold text-rose-500 dark:text-rose-400 mt-1 flex items-center gap-1"
      >
        <AlertCircle class="w-3.5 h-3.5 shrink-0" />
        <span>{{ fieldError('low_stock_default_threshold') }}</span>
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { TrendingDown, RotateCcw, Info, AlertCircle } from 'lucide-vue-next';
import { normalize } from '../../../helpers/decimal';

const props = defineProps({
  form: { type: Object, default: () => ({ low_stock_default_threshold: '5.000' }) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
});

const emit = defineEmits(['update:field', 'reset-field']);

const DEFAULT_THRESHOLD = '5.000';

const isThresholdModified = computed(() => {
  const current = props.form?.low_stock_default_threshold;
  if (current === undefined || current === null || current === '') return false;
  try {
    return normalize(current) !== DEFAULT_THRESHOLD;
  } catch {
    return String(current) !== DEFAULT_THRESHOLD;
  }
});

const fieldError = (key) => {
  const err = props.errors?.[key];
  if (!err) return null;
  return Array.isArray(err) ? err[0] : err;
};

const handleBlur = (e) => {
  const val = e.target.value.trim();
  if (val === '') {
    emit('update:field', 'low_stock_default_threshold', DEFAULT_THRESHOLD);
    return;
  }
  try {
    // Normalizes to scale 3 half-up string via helpers/decimal.js
    const normalized = normalize(val);
    emit('update:field', 'low_stock_default_threshold', normalized);
  } catch {
    emit('update:field', 'low_stock_default_threshold', val);
  }
};
</script>
