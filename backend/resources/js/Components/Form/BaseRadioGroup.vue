<template>
  <div class="w-full flex flex-col gap-2" :class="wrapperClass">
    <!-- Group Label -->
    <label
      v-if="label"
      class="block text-xs font-black text-slate-700 dark:text-slate-200 select-none"
      :class="{ 'text-rose-500': hasError }"
    >
      {{ label }}
      <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
    </label>

    <!-- Options List (Grid or Column) -->
    <div
      class="grid gap-2"
      :class="[
        columns ? `grid-cols-${columns}` : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3'
      ]"
    >
      <label
        v-for="opt in options"
        :key="getOptionValue(opt)"
        class="flex items-center gap-3 p-3 rounded-2xl border transition-all duration-200 cursor-pointer select-none min-h-[48px] shadow-2xs"
        :class="[
          isSelected(opt)
            ? 'bg-theme-light border-theme-primary ring-1 ring-theme-primary/30 text-slate-900 dark:text-white'
            : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 text-slate-700 dark:text-slate-300',
          disabled ? 'opacity-50 cursor-not-allowed' : ''
        ]"
      >
        <input
          type="radio"
          :name="name || groupName"
          :value="getOptionValue(opt)"
          v-model="model"
          :disabled="disabled"
          class="sr-only"
        />

        <!-- Custom Radio Circle Indicator -->
        <div
          class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors"
          :class="[
            isSelected(opt)
              ? 'border-theme-primary bg-theme-primary'
              : 'border-slate-400 dark:border-slate-500 bg-transparent'
          ]"
        >
          <div v-if="isSelected(opt)" class="w-1.5 h-1.5 rounded-full bg-white"></div>
        </div>

        <div class="flex flex-col min-w-0">
          <div class="flex items-center gap-1.5 font-black text-xs truncate">
            <span v-if="opt.icon">{{ opt.icon }}</span>
            <span>{{ getOptionLabel(opt) }}</span>
          </div>
          <span v-if="opt.description" class="text-[10px] text-slate-400 font-medium truncate">
            {{ opt.description }}
          </span>
        </div>
      </label>
    </div>

    <!-- Error Message -->
    <p
      v-if="hasError"
      class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1 mt-0.5"
    >
      <AlertCircle class="w-3.5 h-3.5 shrink-0" />
      <span>{{ errorMessage }}</span>
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { AlertCircle } from 'lucide-vue-next';

const props = defineProps({
  label: { type: String, default: '' },
  name: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  valueKey: { type: String, default: 'value' },
  labelKey: { type: String, default: 'label' },
  columns: { type: [Number, String], default: null },
  error: { type: [String, Array], default: null },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' }
});

const model = defineModel({
  type: [String, Number, Boolean],
  default: null
});

const groupName = 'radio-grp-' + Math.random().toString(36).substring(2, 9);

const getOptionValue = (opt) => (typeof opt === 'object' && opt !== null ? opt[props.valueKey] : opt);
const getOptionLabel = (opt) => (typeof opt === 'object' && opt !== null ? opt[props.labelKey] : opt);
const isSelected = (opt) => model.value === getOptionValue(opt);

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});
</script>