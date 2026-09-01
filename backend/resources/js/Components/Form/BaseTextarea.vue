<template>
  <div class="w-full flex flex-col gap-1.5" :class="wrapperClass">
    <!-- Header with Label & Character Counter -->
    <div class="flex items-center justify-between">
      <label
        v-if="label"
        :for="textareaId"
        class="block text-xs font-black text-slate-700 dark:text-slate-200 cursor-pointer select-none transition-colors"
        :class="{ 'text-rose-500 dark:text-rose-400': hasError }"
      >
        {{ label }}
        <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
      </label>

      <span v-if="maxlength" class="text-[10px] font-mono text-slate-400 dark:text-slate-500">
        {{ currentLength }} / {{ maxlength }}
      </span>
    </div>

    <!-- Native Textarea -->
    <textarea
      :id="textareaId"
      ref="textareaRef"
      v-model="model"
      :name="name"
      :rows="rows"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :maxlength="maxlength"
      :aria-invalid="hasError ? 'true' : 'false'"
      :aria-describedby="hasError ? `${textareaId}-error` : (hint ? `${textareaId}-hint` : undefined)"
      class="w-full px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-200 outline-hidden resize-y disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-900/50"
      :class="[
        hasError
          ? 'border-rose-500 dark:border-rose-500/80 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
          : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20',
        textareaClass
      ]"
      v-bind="$attrs"
      @focus="$emit('focus', $event)"
      @blur="$emit('blur', $event)"
    ></textarea>

    <!-- Error Message -->
    <p
      v-if="hasError"
      :id="`${textareaId}-error`"
      class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1 mt-0.5 animate-in fade-in"
    >
      <AlertCircle class="w-3.5 h-3.5 shrink-0" />
      <span>{{ errorMessage }}</span>
    </p>

    <!-- Helper / Hint Text -->
    <p
      v-else-if="hint"
      :id="`${textareaId}-hint`"
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
  rows: { type: [Number, String], default: 3 },
  placeholder: { type: String, default: '' },
  maxlength: { type: [Number, String], default: null },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' },
  textareaClass: { type: String, default: '' }
});

defineEmits(['focus', 'blur']);

const model = defineModel({
  type: String,
  default: ''
});

const textareaRef = ref(null);
const autoId = 'txt-' + Math.random().toString(36).substring(2, 9);
const textareaId = computed(() => props.id || autoId);

const currentLength = computed(() => (model.value || '').length);

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});

defineExpose({
  textareaRef,
  focus: () => textareaRef.value?.focus()
});
</script>