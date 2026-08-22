<template>
  <div class="w-full flex flex-col gap-1.5" :class="wrapperClass">
    <!-- Label -->
    <label
      v-if="label"
      class="block text-xs font-black text-slate-700 dark:text-slate-200 select-none transition-colors"
      :class="{ 'text-rose-500 dark:text-rose-400': hasError }"
    >
      {{ label }}
      <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
    </label>

    <!-- VueDatePicker Component Wrapper -->
    <div class="relative w-full datepicker-rtl-container font-tajawal">
      <VueDatePicker
        v-model="model"
        :range="range"
        :enable-time-picker="enableTimePicker"
        :locale="locale"
        :format="format || (range ? 'yyyy-MM-dd' : 'yyyy-MM-dd')"
        :auto-apply="autoApply"
        :disabled="disabled"
        :readonly="readonly"
        :placeholder="placeholder || 'اختر التاريخ...'"
        :dark="isDark"
        input-class-name="base-datepicker-input"
        menu-class-name="base-datepicker-menu shadow-2xl rounded-2xl"
        v-bind="$attrs"
        @update:model-value="$emit('change', $event)"
      >
        <template #input-icon>
          <Calendar class="w-4 h-4 text-slate-400 dark:text-slate-500 mr-3" />
        </template>
        <template #clear-icon="{ clear }">
          <button type="button" @click="clear" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-3.5 h-3.5" />
          </button>
        </template>
      </VueDatePicker>
    </div>

    <!-- Error Message -->
    <p
      v-if="hasError"
      class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1 mt-0.5 animate-in fade-in"
    >
      <AlertCircle class="w-3.5 h-3.5 shrink-0" />
      <span>{{ errorMessage }}</span>
    </p>

    <!-- Helper / Hint Text -->
    <p
      v-else-if="hint"
      class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-0.5"
    >
      {{ hint }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { Calendar, X, AlertCircle } from 'lucide-vue-next';

defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  range: { type: Boolean, default: false },
  enableTimePicker: { type: Boolean, default: false },
  format: { type: String, default: null },
  locale: { type: String, default: 'ar' },
  autoApply: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  wrapperClass: { type: String, default: '' }
});

defineEmits(['change']);

const model = defineModel({
  type: [String, Date, Array, Object],
  default: null
});

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});
</script>

<style>
.datepicker-rtl-container .dp__input {
  min-height: 44px !important;
  padding: 0.625rem 2.5rem 0.625rem 1rem !important;
  font-size: 0.875rem !important;
  font-weight: 700 !important;
  border-radius: 0.75rem !important;
  background-color: var(--dp-background-color, #f8fafc) !important;
  border: 1px solid #cbd5e1 !important;
  transition: all 0.2s ease !important;
  direction: rtl !important;
  text-align: right !important;
}

.dark .datepicker-rtl-container .dp__input {
  background-color: #1e293b !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

.datepicker-rtl-container .dp__input:focus {
  border-color: var(--color-primary, #f59e0b) !important;
  box-shadow: 0 0 0 2px rgba(var(--color-primary-rgb, 245, 158, 11), 0.2) !important;
}

.datepicker-rtl-container .dp__menu {
  border-radius: 1rem !important;
  font-family: 'Tajawal', 'Cairo', sans-serif !important;
  border: 1px solid #e2e8f0 !important;
  direction: rtl !important;
}

.dark .datepicker-rtl-container .dp__menu {
  background-color: #0f172a !important;
  border-color: #1e293b !important;
}

.datepicker-rtl-container .dp__active_date {
  background-color: var(--color-primary, #f59e0b) !important;
  color: #020617 !important;
  font-weight: 900 !important;
}
</style>