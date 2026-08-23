<template>
  <div class="w-full flex flex-col gap-1.5 font-tajawal" :class="wrapperClass">
    <!-- Label -->
    <label
      v-if="label"
      class="block text-xs font-black text-slate-700 dark:text-slate-200 select-none transition-colors"
      :class="{ 'text-rose-500 dark:text-rose-400': hasError }"
    >
      {{ label }}
      <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
    </label>

    <!-- Input Wrapper with Icon & Clear button -->
    <div class="relative w-full select-none" :class="{ 'opacity-60 pointer-events-none': disabled }">
      <div class="relative flex items-center">
        <!-- Calendar Icon -->
        <span class="absolute start-3.5 text-sm text-slate-400 pointer-events-none z-10">
          <Calendar class="w-4 h-4 text-slate-400 dark:text-slate-500" />
        </span>

        <!-- Flatpickr Input -->
        <input
          ref="inputRef"
          type="text"
          :placeholder="placeholder || $t('common.select_date')"
          :disabled="disabled"
          readonly
          class="w-full min-h-[44px] ps-10 pe-9 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 font-mono text-xs cursor-pointer focus:ring-2 focus:ring-theme-primary/50 focus:border-theme-primary focus:outline-none transition shadow-xs"
          :class="[
            modelValue ? 'font-bold text-theme-primary' : '',
            hasError ? 'border-rose-500 dark:border-rose-500 focus:ring-rose-500/20' : '',
            inputClass
          ]"
        />

        <!-- Clear Button -->
        <button
          v-if="clearable && modelValue && !disabled"
          @click="clearDate"
          type="button"
          class="absolute end-2.5 min-h-[32px] min-w-[32px] rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-rose-500 flex items-center justify-center text-xs transition z-10 active:scale-90 cursor-pointer"
          :title="$t('common.cancel')"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>
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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import flatpickr from 'flatpickr';
import { Arabic } from 'flatpickr/dist/l10n/ar.js';
import 'flatpickr/dist/flatpickr.min.css';
import { Calendar, X, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
  modelValue: { type: [String, Array, Date, null], default: null },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  mode: { type: String, default: 'single' }, // 'single' | 'range'
  enableTime: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  wrapperClass: { type: String, default: '' },
  inputClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear']);

const inputRef = ref(null);
let fpInstance = null;

const isRTL = computed(() => document.documentElement.dir === 'rtl' || !document.documentElement.dir);

onMounted(() => {
  if (inputRef.value) {
    fpInstance = flatpickr(inputRef.value, {
      locale: isRTL.value ? Arabic : undefined,
      mode: props.mode,
      enableTime: props.enableTime,
      dateFormat: props.enableTime ? 'Y-m-d H:i' : 'Y-m-d',
      defaultDate: props.modelValue || undefined,
      disableMobile: true,
      onChange: (selectedDates, dateStr) => {
        emit('update:modelValue', dateStr);
        emit('change', dateStr, selectedDates);
      },
    });
  }
});

watch(() => props.modelValue, (newVal) => {
  if (fpInstance && newVal !== fpInstance.input.value) {
    fpInstance.setDate(newVal || '', false);
  }
});

onUnmounted(() => {
  if (fpInstance) {
    fpInstance.destroy();
  }
});

const clearDate = (e) => {
  e?.stopPropagation();
  if (fpInstance) {
    fpInstance.clear();
  }
  emit('update:modelValue', '');
  emit('clear');
  emit('change', '', []);
};

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
/* 🌟 Universal Flatpickr Calendar Styling - Flawless Light & Dark Mode */
.flatpickr-calendar {
  font-family: 'Tajawal', 'Cairo', sans-serif !important;
  border-radius: 1.25rem !important;
  padding: 12px !important;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
  border: 1px solid #e2e8f0 !important;
  background: #ffffff !important;
  color: #0f172a !important;
  z-index: 99999 !important;
}

html.dark .flatpickr-calendar,
.dark .flatpickr-calendar {
  background: #0f172a !important;
  border-color: #1e293b !important;
  color: #f8fafc !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
}

/* Month Selector Dropdown */
.flatpickr-monthDropdown-months {
  background: #f1f5f9 !important;
  color: #0f172a !important;
  font-family: 'Tajawal', sans-serif !important;
  font-weight: 800 !important;
  font-size: 0.875rem !important;
  border-radius: 0.6rem !important;
  padding: 4px 8px !important;
  border: 1px solid #cbd5e1 !important;
  outline: none !important;
  cursor: pointer !important;
}

.flatpickr-monthDropdown-months:hover {
  background: #e2e8f0 !important;
}

.flatpickr-monthDropdown-months .flatpickr-monthDropdown-month,
.flatpickr-monthDropdown-months option {
  background-color: #ffffff !important;
  color: #0f172a !important;
  font-family: 'Tajawal', sans-serif !important;
  font-weight: 700 !important;
  padding: 6px 10px !important;
}

html.dark .flatpickr-monthDropdown-months,
.dark .flatpickr-monthDropdown-months {
  background: #1e293b !important;
  color: #f8fafc !important;
  border-color: #334155 !important;
}

html.dark .flatpickr-monthDropdown-months:hover,
.dark .flatpickr-monthDropdown-months:hover {
  background: #334155 !important;
}

html.dark .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month,
html.dark .flatpickr-monthDropdown-months option,
.dark .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month,
.dark .flatpickr-monthDropdown-months option {
  background-color: #0f172a !important;
  color: #f8fafc !important;
}

/* Year Input */
.flatpickr-current-month input.cur-year {
  color: #0f172a !important;
  font-weight: 800 !important;
  font-size: 0.875rem !important;
}

html.dark .flatpickr-current-month input.cur-year,
.dark .flatpickr-current-month input.cur-year {
  color: #f8fafc !important;
}

/* Weekdays */
.flatpickr-weekday {
  color: #64748b !important;
  font-weight: 800 !important;
  font-size: 0.75rem !important;
}

html.dark .flatpickr-weekday,
.dark .flatpickr-weekday {
  color: #94a3b8 !important;
}

/* Days */
.flatpickr-day {
  color: #1e293b !important;
  border-radius: 0.6rem !important;
  font-weight: 700 !important;
  transition: all 0.15s ease !important;
}

.flatpickr-day:hover {
  background: #f1f5f9 !important;
  color: #0f172a !important;
}

html.dark .flatpickr-day,
.dark .flatpickr-day {
  color: #e2e8f0 !important;
}

html.dark .flatpickr-day:hover,
.dark .flatpickr-day:hover {
  background: #334155 !important;
  color: #ffffff !important;
}

/* Selected Days */
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange {
  background: var(--color-primary, #10b981) !important;
  border-color: var(--color-primary, #10b981) !important;
  color: #ffffff !important;
  font-weight: 900 !important;
  border-radius: 0.75rem !important;
}

.flatpickr-day.selected:hover,
.flatpickr-day.startRange:hover,
.flatpickr-day.endRange:hover {
  background: var(--color-primary, #10b981) !important;
  color: #ffffff !important;
}

.flatpickr-day.inRange {
  background: rgba(var(--color-primary-rgb, 16, 185, 129), 0.15) !important;
  border-color: transparent !important;
}

.flatpickr-day.flatpickr-disabled,
.flatpickr-day.prevMonthDay,
.flatpickr-day.nextMonthDay {
  color: #cbd5e1 !important;
}

html.dark .flatpickr-day.flatpickr-disabled,
html.dark .flatpickr-day.prevMonthDay,
html.dark .flatpickr-day.nextMonthDay,
.dark .flatpickr-day.flatpickr-disabled,
.dark .flatpickr-day.prevMonthDay,
.dark .flatpickr-day.nextMonthDay {
  color: #475569 !important;
}

/* Month Navigation Arrows */
.flatpickr-months .flatpickr-prev-month,
.flatpickr-months .flatpickr-next-month {
  color: #64748b !important;
  fill: #64748b !important;
  border-radius: 0.5rem !important;
  padding: 4px !important;
}

.flatpickr-months .flatpickr-prev-month:hover,
.flatpickr-months .flatpickr-next-month:hover {
  background: #f1f5f9 !important;
}

.flatpickr-months .flatpickr-prev-month:hover svg,
.flatpickr-months .flatpickr-next-month:hover svg {
  fill: var(--color-primary, #10b981) !important;
}

html.dark .flatpickr-months .flatpickr-prev-month,
html.dark .flatpickr-months .flatpickr-next-month,
.dark .flatpickr-months .flatpickr-prev-month,
.dark .flatpickr-months .flatpickr-next-month {
  color: #94a3b8 !important;
  fill: #94a3b8 !important;
}

html.dark .flatpickr-months .flatpickr-prev-month:hover,
html.dark .flatpickr-months .flatpickr-next-month:hover,
.dark .flatpickr-months .flatpickr-prev-month:hover,
.dark .flatpickr-months .flatpickr-next-month:hover {
  background: #1e293b !important;
}

html.dark .flatpickr-months .flatpickr-prev-month:hover svg,
html.dark .flatpickr-months .flatpickr-next-month:hover svg,
.dark .flatpickr-months .flatpickr-prev-month:hover svg,
.dark .flatpickr-months .flatpickr-next-month:hover svg {
  fill: var(--color-primary, #10b981) !important;
}

.flatpickr-current-month .numInputWrapper span.arrowUp:after {
  border-bottom-color: #0f172a !important;
}
.flatpickr-current-month .numInputWrapper span.arrowDown:after {
  border-top-color: #0f172a !important;
}

html.dark .flatpickr-current-month .numInputWrapper span.arrowUp:after,
.dark .flatpickr-current-month .numInputWrapper span.arrowUp:after {
  border-bottom-color: #f8fafc !important;
}
html.dark .flatpickr-current-month .numInputWrapper span.arrowDown:after,
.dark .flatpickr-current-month .numInputWrapper span.arrowDown:after {
  border-top-color: #f8fafc !important;
}
</style>
