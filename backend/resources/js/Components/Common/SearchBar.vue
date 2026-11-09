<script setup>
import DynamicIcon from 'DynamicIcon.vue';
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  debounce: {
    type: Number,
    default: 300,
  },
  icon: {
    type: String,
    default: 'search',
  },
  inputClass: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'search', 'clear']);

const localValue = ref(props.modelValue);
let timer = null;

watch(
  () => props.modelValue,
  (newVal) => {
    localValue.value = newVal;
  }
);

const onInput = (e) => {
  const val = e.target.value;
  localValue.value = val;
  emit('update:modelValue', val);

  clearTimeout(timer);
  timer = setTimeout(() => {
    emit('search', val);
  }, props.debounce);
};

const clear = () => {
  localValue.value = '';
  emit('update:modelValue', '');
  emit('search', '');
  emit('clear');
};
</script>

<template>
  <div class="relative flex items-center w-full font-tajawal">
    <input
      :value="localValue"
      type="text"
      :placeholder="placeholder"
      class="w-full ps-10 pe-10 py-2.5 bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none transition shadow-inner"
      :class="inputClass"
      @input="onInput"
    />

    <span class="absolute inset-y-0 start-0 ps-3.5 flex items-center text-slate-400 text-xs pointer-events-none">
      <DynamicIcon :name="icon" class="w-4 h-4" />
    </span>

    <button
      v-if="localValue"
      type="button"
      class="absolute inset-y-0 end-0 pe-3.5 ps-2 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-900 dark:text-slate-200 text-xs cursor-pointer transition"
      @click="clear"
    >
      <X class="w-3.5 h-3.5" />
    </button>
  </div>
</template>
