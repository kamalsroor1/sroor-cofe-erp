<template>
  <div class="flex flex-col gap-1" :class="wrapperClass">
    <label
      :for="switchId"
      class="inline-flex items-center justify-between gap-4 cursor-pointer select-none min-h-[44px] group"
      :class="{ 'opacity-50 cursor-not-allowed': disabled }"
    >
      <!-- Label & Description -->
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

      <!-- Native Checkbox hidden with Accessible Toggle Track -->
      <div class="relative inline-flex items-center shrink-0">
        <input
          :id="switchId"
          v-model="model"
          type="checkbox"
          :disabled="disabled"
          class="sr-only peer"
          v-bind="$attrs"
          @change="$emit('change', $event)"
        />
        <div
          class="w-12 h-7 bg-slate-300 dark:bg-slate-700 peer-focus-visible:ring-2 peer-focus-visible:ring-theme-primary rounded-full peer peer-checked:bg-theme-primary transition-colors duration-200 shadow-inner"
        ></div>
        <div
          class="absolute left-1 top-1 bg-white w-5 h-5 rounded-full transition-transform duration-200 shadow-md peer-checked:translate-x-5 flex items-center justify-center text-[10px] font-black"
        >
          <span v-if="model" class="text-theme-primary">✓</span>
        </div>
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
import { AlertCircle } from 'lucide-vue-next';

defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  id: { type: String, default: null },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  error: { type: [String, Array], default: null },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' }
});

defineEmits(['change']);

const model = defineModel({
  type: Boolean,
  default: false
});

const autoId = 'sw-' + Math.random().toString(36).substring(2, 9);
const switchId = computed(() => props.id || autoId);

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});
</script>