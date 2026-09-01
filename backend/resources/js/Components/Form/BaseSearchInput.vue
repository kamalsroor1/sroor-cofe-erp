<template>
  <div class="relative w-full flex items-center" :class="wrapperClass">
    <!-- Leading Search Icon -->
    <Search class="w-4 h-4 absolute right-3.5 text-slate-400 dark:text-slate-500 pointer-events-none transition-colors" />

    <!-- Native Search Input -->
    <input
      ref="inputRef"
      v-model="model"
      type="text"
      :placeholder="placeholder || 'بحث سريع...'"
      :disabled="disabled"
      class="w-full min-h-[44px] pr-10 pl-10 py-2.5 text-base sm:text-sm font-bold rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 transition-all duration-200 outline-hidden hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 shadow-2xs"
      :class="inputClass"
      v-bind="$attrs"
      @keydown.esc="clear"
      @input="handleInput"
    />

    <!-- Trailing Action: Loading Spinner or Instant Clear Button -->
    <div class="absolute left-3 flex items-center">
      <Loader2 v-if="loading" class="w-4 h-4 animate-spin text-theme-primary" />
      <button
        v-else-if="model"
        type="button"
        @click="clear"
        class="p-1 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
        title="تفريغ البحث (ESC)"
      >
        <X class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Search, X, Loader2 } from 'lucide-vue-next';

defineOptions({
  inheritAttrs: false
});

const props = defineProps({
  placeholder: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  debounce: { type: Number, default: 0 },
  wrapperClass: { type: String, default: '' },
  inputClass: { type: String, default: '' }
});

const emit = defineEmits(['search', 'clear']);

const model = defineModel({
  type: String,
  default: ''
});

const inputRef = ref(null);
let debounceTimer = null;

const handleInput = () => {
  if (props.debounce > 0) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      emit('search', model.value);
    }, props.debounce);
  } else {
    emit('search', model.value);
  }
};

const clear = () => {
  model.value = '';
  emit('search', '');
  emit('clear');
  inputRef.value?.focus();
};

defineExpose({
  inputRef,
  focus: () => inputRef.value?.focus()
});
</script>