<template>
  <div class="w-full flex flex-col gap-1.5" :class="wrapperClass">
    <!-- Label -->
    <label
      v-if="label"
      :for="inputId"
      class="block text-xs font-black text-slate-700 dark:text-slate-200 cursor-pointer select-none transition-colors"
      :class="{ 'text-rose-500 dark:text-rose-400': hasError }"
    >
      {{ label }}
      <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
    </label>

    <!-- Number Input Container -->
    <div class="relative flex items-center w-full">
      <!-- Native Number / Decimal Input -->
      <input
        :id="inputId"
        ref="inputRef"
        v-model="model"
        type="number"
        :name="name"
        :placeholder="placeholder"
        :min="min"
        :max="max"
        :step="step"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        inputmode="decimal"
        :aria-invalid="hasError ? 'true' : 'false'"
        :aria-describedby="hasError ? `${inputId}-error` : (hint ? `${inputId}-hint` : undefined)"
        class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold font-mono rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-200 outline-hidden disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-900/50"
        :class="[
          hasError
            ? 'border-rose-500 dark:border-rose-500/80 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
            : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20',
          prefix ? 'pr-12' : '',
          suffix || showStepper ? 'pl-16' : '',
          inputClass
        ]"
        v-bind="$attrs"
        @focus="$emit('focus', $event)"
        @blur="$emit('blur', $event)"
        @keydown="$emit('keydown', $event)"
      />

      <!-- Currency / Unit Prefix (Right in RTL) -->
      <div
        v-if="prefix"
        class="absolute right-3 flex items-center pointer-events-none text-xs font-black text-slate-400 dark:text-slate-500 select-none"
      >
        {{ prefix }}
      </div>

      <!-- Currency / Unit Suffix (Left in RTL) or Stepper Buttons -->
      <div class="absolute left-2.5 flex items-center gap-1">
        <span v-if="suffix" class="text-xs font-black text-slate-400 dark:text-slate-500 select-none px-1">
          {{ suffix }}
        </span>

        <!-- Quick Stepper Buttons -->
        <div v-if="showStepper && !disabled && !readonly" class="flex items-center gap-0.5 bg-slate-200 dark:bg-slate-700 p-0.5 rounded-lg">
          <button
            type="button"
            tabindex="-1"
            @click="decrement"
            class="w-6 h-6 flex items-center justify-center rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition cursor-pointer active:scale-95 text-xs font-black shadow-2xs"
            title="تقليل"
          >
            -
          </button>
          <button
            type="button"
            tabindex="-1"
            @click="increment"
            class="w-6 h-6 flex items-center justify-center rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition cursor-pointer active:scale-95 text-xs font-black shadow-2xs"
            title="زيادة"
          >
            +
          </button>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <p
      v-if="hasError"
      :id="`${inputId}-error`"
      class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1 mt-0.5 animate-in fade-in"
    >
      <AlertCircle class="w-3.5 h-3.5 shrink-0" />
      <span>{{ errorMessage }}</span>
    </p>

    <!-- Helper / Hint Text -->
    <p
      v-else-if="hint"
      :id="`${inputId}-hint`"
      class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-0.5"
    >
      {{ hint }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { AlertCircle } from 'lucide-vue-next';

defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  id: { type: String, default: null },
  label: { type: String, default: '' },
  name: { type: String, default: '' },
  placeholder: { type: String, default: '0.00' },
  min: { type: [Number, String], default: null },
  max: { type: [Number, String], default: null },
  step: { type: [Number, String], default: '0.001' },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
  showStepper: { type: Boolean, default: false },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' },
  inputClass: { type: String, default: '' }
});

defineEmits(['focus', 'blur', 'keydown']);

const model = defineModel({
  type: [Number, String],
  default: ''
});

const inputRef = ref(null);
const autoId = 'num-input-' + Math.random().toString(36).substring(2, 9);
const inputId = computed(() => props.id || autoId);

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});

const stepVal = computed(() => parseFloat(props.step) || 1);

const increment = () => {
  const cur = parseFloat(model.value) || 0;
  const next = cur + stepVal.value;
  if (props.max !== null && next > parseFloat(props.max)) return;
  model.value = parseFloat(next.toFixed(3));
};

const decrement = () => {
  const cur = parseFloat(model.value) || 0;
  const next = cur - stepVal.value;
  if (props.min !== null && next < parseFloat(props.min)) return;
  model.value = parseFloat(next.toFixed(3));
};

defineExpose({
  inputRef,
  focus: () => inputRef.value?.focus(),
  select: () => inputRef.value?.select()
});
</script>