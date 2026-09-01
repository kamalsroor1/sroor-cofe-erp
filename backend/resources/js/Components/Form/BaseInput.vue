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

    <!-- Input Wrapper with Leading / Trailing Addons & Password Toggle -->
    <div class="relative flex items-center w-full">
      <!-- Leading Icon / Addon -->
      <div
        v-if="$slots.leading || leadingIcon"
        class="absolute right-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500 transition-colors"
        :class="{ 'text-rose-500 dark:text-rose-400': hasError }"
      >
        <slot name="leading">
          <component :is="leadingIcon" class="w-4 h-4" />
        </slot>
      </div>

      <!-- Native Input -->
      <input
        :id="inputId"
        ref="inputRef"
        v-model="model"
        :type="resolvedType"
        :name="name"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :autocomplete="autocomplete"
        :inputmode="inputmode"
        :maxlength="maxlength"
        :minlength="minlength"
        :aria-invalid="hasError ? 'true' : 'false'"
        :aria-describedby="hasError ? `${inputId}-error` : (hint ? `${inputId}-hint` : undefined)"
        class="w-full min-h-[44px] px-3.5 py-2.5 text-base sm:text-sm font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-200 outline-hidden disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-900/50"
        :class="[
          hasError
            ? 'border-rose-500 dark:border-rose-500/80 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
            : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20',
          $slots.leading || leadingIcon ? 'pr-10' : '',
          $slots.trailing || trailingIcon || type === 'password' || isClearable ? 'pl-10' : '',
          inputClass
        ]"
        v-bind="$attrs"
        @focus="$emit('focus', $event)"
        @blur="$emit('blur', $event)"
        @keydown="$emit('keydown', $event)"
      />

      <!-- Trailing Addon / Password Toggle / Clear Button -->
      <div class="absolute left-3 flex items-center gap-1.5 text-slate-400 dark:text-slate-500">
        <!-- Clear Button -->
        <button
          v-if="isClearable && model && !disabled && !readonly"
          type="button"
          tabindex="-1"
          @click="model = ''"
          class="p-1 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
          title="مسح"
        >
          <X class="w-3.5 h-3.5" />
        </button>

        <!-- Password Visibility Toggle -->
        <button
          v-if="type === 'password'"
          type="button"
          tabindex="-1"
          @click="showPassword = !showPassword"
          class="p-1 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
          :title="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
        >
          <EyeOff v-if="showPassword" class="w-4 h-4" />
          <Eye v-else class="w-4 h-4" />
        </button>

        <!-- Custom Trailing Slot or Icon -->
        <slot name="trailing">
          <component :is="trailingIcon" v-if="trailingIcon && type !== 'password'" class="w-4 h-4" />
        </slot>
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
import { Eye, EyeOff, AlertCircle, X } from 'lucide-vue-next';

defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  id: { type: String, default: null },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  name: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  autocomplete: { type: String, default: 'off' },
  inputmode: { type: String, default: null },
  maxlength: { type: [Number, String], default: null },
  minlength: { type: [Number, String], default: null },
  clearable: { type: Boolean, default: false },
  leadingIcon: { type: [Object, Function, String], default: null },
  trailingIcon: { type: [Object, Function, String], default: null },
  wrapperClass: { type: String, default: '' },
  inputClass: { type: String, default: '' }
});

defineEmits(['focus', 'blur', 'keydown']);

const model = defineModel({
  type: [String, Number],
  default: ''
});

const inputRef = ref(null);
const showPassword = ref(false);
const autoId = 'input-' + Math.random().toString(36).substring(2, 9);
const inputId = computed(() => props.id || autoId);

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});

const isClearable = computed(() => props.clearable && props.type !== 'password');

const resolvedType = computed(() => {
  if (props.type === 'password') {
    return showPassword.value ? 'text' : 'password';
  }
  return props.type;
});

defineExpose({
  inputRef,
  focus: () => inputRef.value?.focus(),
  select: () => inputRef.value?.select()
});
</script>