<template>
  <div class="flex flex-col gap-1" :class="wrapperClass">
    <label
      :for="checkboxId"
      class="inline-flex items-center gap-3 cursor-pointer select-none group min-h-[44px]"
      :class="{ 'opacity-50 cursor-not-allowed': disabled }"
    >
      <!-- Custom Checkbox Box -->
      <div class="relative flex items-center justify-center shrink-0">
        <input
          :id="checkboxId"
          v-model="model"
          type="checkbox"
          :value="value"
          :disabled="disabled"
          :required="required"
          class="sr-only peer"
          v-bind="$attrs"
          @change="$emit('change', $event)"
        />
        <div
          class="w-5 h-5 rounded-lg border-2 bg-white dark:bg-slate-800 transition-all duration-200 flex items-center justify-center shadow-2xs peer-focus-visible:ring-2 peer-focus-visible:ring-theme-primary/40"
          :class="[
            hasError
              ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/30'
              : model
                ? 'bg-theme-primary border-theme-primary text-white'
                : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600'
          ]"
        >
          <Check v-if="model" class="w-3.5 h-3.5 stroke-[3] text-slate-950" />
        </div>
      </div>

      <!-- Label Text & Subtext -->
      <div class="flex flex-col">
        <span
          class="text-sm font-bold text-slate-800 dark:text-slate-200 group-hover:text-slate-900 dark:group-hover:text-white transition-colors"
          :class="{ 'text-rose-500': hasError }"
        >
          {{ label }}
          <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
        </span>
        <span v-if="description" class="text-xs text-slate-500 dark:text-slate-400 font-medium">
          {{ description }}
        </span>
      </div>
    </label>

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
import { Check, AlertCircle } from 'lucide-vue-next';

defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  id: { type: String, default: null },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  value: { type: [String, Number, Boolean, Object], default: true },
  error: { type: [String, Array], default: null },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' }
});

defineEmits(['change']);

const model = defineModel({
  type: [Boolean, Array],
  default: false
});

const autoId = 'chk-' + Math.random().toString(36).substring(2, 9);
const checkboxId = computed(() => props.id || autoId);

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});
</script>