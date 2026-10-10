<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-6 font-tajawal transition-colors"
  >
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-2xl bg-theme-light border border-theme-border text-theme-primary flex items-center justify-center shrink-0"
        >
          <Globe class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">
            {{ $t('settings.localization_section_title') }}
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $t('settings.localization_section_sub') }}
          </p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- 1. Currency -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label for="setting-currency" class="block text-xs font-black text-slate-700 dark:text-slate-200">
            {{ $t('settings.currency') }}
            <span class="text-rose-500 mr-0.5">*</span>
          </label>
          <div class="flex items-center gap-2">
            <span
              v-if="isCurrencyModified"
              class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
            >
              {{ $t('settings.modified_from_default') }}
            </span>
            <button
              v-if="isCurrencyModified && canManage && !isLegacyCurrency"
              type="button"
              @click="$emit('reset-field', 'currency')"
              class="text-[11px] font-bold text-theme-primary hover:underline cursor-pointer flex items-center gap-1"
            >
              <RotateCcw class="w-3 h-3" />
              <span>{{ $t('settings.reset_to_default') }}</span>
            </button>
          </div>
        </div>

        <!-- Legacy Currency Read-Only Alert & Display -->
        <div v-if="isLegacyCurrency" class="space-y-2">
          <div
            id="setting-currency"
            class="min-h-[44px] px-3.5 py-2.5 rounded-xl border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-200 flex items-center justify-between font-bold text-sm"
          >
            <span>{{ form.currency }}</span>
            <span class="text-xs px-2 py-0.5 rounded-md bg-amber-200 dark:bg-amber-900/60 font-black">
              {{ $t('settings.read_only_mode') }}
            </span>
          </div>
          <p class="text-xs text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1.5">
            <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
            <span>{{ $t('settings.currency_legacy_readonly') }}</span>
          </p>
        </div>

        <!-- Allowed Currencies Selector -->
        <div v-else class="relative">
          <select
            id="setting-currency"
            :value="form.currency || 'EGP'"
            @change="$emit('update:field', 'currency', $event.target.value)"
            :disabled="!canManage"
            class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 outline-hidden transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            :class="{ 'border-rose-500 dark:border-rose-500/80 focus:border-rose-500': fieldError('currency') }"
          >
            <option v-for="c in allowedCurrencies" :key="c.code" :value="c.code">
              {{ $t(c.labelKey) }}
            </option>
          </select>
        </div>

        <p v-if="fieldError('currency')" class="text-xs font-bold text-rose-500 dark:text-rose-400 mt-1">
          {{ fieldError('currency') }}
        </p>
      </div>

      <!-- 2. Timezone (Searchable select / filtered dropdown) -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label for="setting-timezone" class="block text-xs font-black text-slate-700 dark:text-slate-200">
            {{ $t('settings.timezone') }}
            <span class="text-rose-500 mr-0.5">*</span>
          </label>
          <div class="flex items-center gap-2">
            <span
              v-if="isTimezoneModified"
              class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
            >
              {{ $t('settings.modified_from_default') }}
            </span>
            <button
              v-if="isTimezoneModified && canManage"
              type="button"
              @click="$emit('reset-field', 'timezone')"
              class="text-[11px] font-bold text-theme-primary hover:underline cursor-pointer flex items-center gap-1"
            >
              <RotateCcw class="w-3 h-3" />
              <span>{{ $t('settings.reset_to_default') }}</span>
            </button>
          </div>
        </div>

        <div class="relative">
          <BaseSelect
            id="setting-timezone"
            :model-value="form.timezone || 'Africa/Cairo'"
            @update:model-value="$emit('update:field', 'timezone', $event)"
            :options="timezoneOptions"
            value-key="value"
            label-key="label"
            :searchable="true"
            :search-placeholder="$t('settings.search_timezone_placeholder')"
            :disabled="!canManage"
            :error="fieldError('timezone')"
          />
        </div>

        <p v-if="fieldError('timezone')" class="text-xs font-bold text-rose-500 dark:text-rose-400 mt-1">
          {{ fieldError('timezone') }}
        </p>
      </div>

      <!-- 3. Business Day Cutoff -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label for="setting-business_day_cutoff" class="block text-xs font-black text-slate-700 dark:text-slate-200">
            {{ $t('settings.business_day_cutoff') }}
            <span class="text-rose-500 mr-0.5">*</span>
          </label>
          <div class="flex items-center gap-2">
            <span
              v-if="isCutoffModified"
              class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
            >
              {{ $t('settings.modified_from_default') }}
            </span>
            <button
              v-if="isCutoffModified && canManage"
              type="button"
              @click="$emit('reset-field', 'business_day_cutoff')"
              class="text-[11px] font-bold text-theme-primary hover:underline cursor-pointer flex items-center gap-1"
            >
              <RotateCcw class="w-3 h-3" />
              <span>{{ $t('settings.reset_to_default') }}</span>
            </button>
          </div>
        </div>

        <div class="relative">
          <input
            id="setting-business_day_cutoff"
            type="time"
            step="60"
            :value="form.business_day_cutoff || '00:00'"
            @input="$emit('update:field', 'business_day_cutoff', $event.target.value)"
            :disabled="!canManage"
            class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 outline-hidden transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed font-mono text-center"
            :class="{
              'border-rose-500 dark:border-rose-500/80 focus:border-rose-500': fieldError('business_day_cutoff'),
            }"
          />
        </div>

        <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-start gap-1">
          <Info class="w-3.5 h-3.5 shrink-0 mt-0.5 text-slate-400 dark:text-slate-500" />
          <span>{{ $t('settings.business_day_cutoff_hint') }}</span>
        </p>

        <p v-if="fieldError('business_day_cutoff')" class="text-xs font-bold text-rose-500 dark:text-rose-400 mt-1">
          {{ fieldError('business_day_cutoff') }}
        </p>
      </div>

      <!-- 4. Number Digits (CTO Decision: Western 123 - Read Only) -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label for="setting-number_digits" class="block text-xs font-black text-slate-700 dark:text-slate-200">
            {{ $t('settings.number_digits') }}
          </label>
          <span
            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
          >
            {{ $t('settings.read_only_mode') }}
          </span>
        </div>

        <div
          id="setting-number_digits"
          class="min-h-[44px] px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-800/60 text-slate-800 dark:text-slate-200 flex items-center justify-between font-bold text-sm"
        >
          <div class="flex items-center gap-2">
            <Hash class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('settings.number_digits_western') }}</span>
          </div>
          <span class="font-mono text-xs text-slate-400 dark:text-slate-500">0123456789</span>
        </div>

        <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-1">
          {{ $t('settings.number_digits_hint') }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Globe, RotateCcw, AlertTriangle, Info, Hash } from 'lucide-vue-next';
import BaseSelect from '../../Form/BaseSelect.vue';

const props = defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
});

defineEmits(['update:field', 'reset-field']);

const fieldError = (key) => {
  const err = props.errors?.[key];
  if (!err) return null;
  return Array.isArray(err) ? err[0] : err;
};

const allowedCurrencies = [
  { code: 'EGP', labelKey: 'settings.currency_egp' },
  { code: 'SAR', labelKey: 'settings.currency_sar' },
  { code: 'AED', labelKey: 'settings.currency_aed' },
  { code: 'KWD', labelKey: 'settings.currency_kwd' },
  { code: 'QAR', labelKey: 'settings.currency_qar' },
  { code: 'USD', labelKey: 'settings.currency_usd' },
];

const isLegacyCurrency = computed(() => {
  const code = props.form.currency;
  if (!code) return false;
  return !allowedCurrencies.some((c) => c.code === code);
});

const isCurrencyModified = computed(() => {
  return props.form.currency && props.form.currency !== 'EGP';
});

const isTimezoneModified = computed(() => {
  return props.form.timezone && props.form.timezone !== 'Africa/Cairo';
});

const isCutoffModified = computed(() => {
  return props.form.business_day_cutoff && props.form.business_day_cutoff !== '00:00';
});

const getAllTimezones = () => {
  try {
    if (typeof Intl !== 'undefined' && typeof Intl.supportedValuesOf === 'function') {
      return Intl.supportedValuesOf('timeZone');
    }
  } catch {
    // Fallback if unsupported
  }
  return [
    'Africa/Cairo',
    'Asia/Riyadh',
    'Asia/Dubai',
    'Asia/Kuwait',
    'Asia/Qatar',
    'Asia/Bahrain',
    'Asia/Amman',
    'Asia/Beirut',
    'Asia/Baghdad',
    'Africa/Tripoli',
    'Africa/Tunis',
    'Africa/Algiers',
    'Africa/Casablanca',
    'Africa/Khartoum',
    'UTC',
    'Europe/London',
    'Europe/Paris',
    'America/New_York',
  ];
};

const timezoneOptions = computed(() => {
  const list = getAllTimezones();
  const current = props.form.timezone;
  const items = [...list];
  if (current && !items.includes(current)) {
    items.unshift(current);
  }
  return items.map((tz) => ({
    value: tz,
    label: tz,
  }));
});
</script>
