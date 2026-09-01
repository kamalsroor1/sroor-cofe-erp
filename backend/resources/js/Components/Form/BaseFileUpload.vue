<template>
  <div class="w-full flex flex-col gap-1.5" :class="wrapperClass">
    <!-- Label -->
    <label
      v-if="label"
      class="block text-xs font-black text-slate-700 dark:text-slate-200 select-none"
      :class="{ 'text-rose-500': hasError }"
    >
      {{ label }}
      <span v-if="required" class="text-rose-500 font-black mr-0.5">*</span>
    </label>

    <!-- Dropzone Box -->
    <div
      class="relative border-2 border-dashed rounded-2xl p-4 transition-all duration-200 flex flex-col items-center justify-center text-center cursor-pointer min-h-[110px]"
      :class="[
        isDragging
          ? 'border-theme-primary bg-theme-light'
          : hasError
            ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/20'
            : 'border-slate-300 dark:border-slate-700 hover:border-theme-primary bg-slate-50 dark:bg-slate-800/50',
        disabled ? 'opacity-50 cursor-not-allowed' : ''
      ]"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
    >
      <input
        ref="fileInputRef"
        type="file"
        :accept="accept"
        :multiple="multiple"
        :disabled="disabled"
        class="sr-only"
        @change="handleFileChange"
      />

      <!-- Image Preview Mode -->
      <div v-if="previewUrl" class="relative group">
        <img
          :src="previewUrl"
          alt="Preview"
          class="w-24 h-24 object-cover rounded-xl shadow-md border border-slate-200 dark:border-slate-700"
        />
        <button
          type="button"
          @click.stop="clearFile"
          class="absolute -top-2 -right-2 p-1 bg-rose-500 text-white rounded-full shadow-lg hover:scale-110 transition cursor-pointer"
          title="حذف الملف"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>

      <!-- Upload Prompt Mode -->
      <div v-else class="flex flex-col items-center gap-2">
        <div class="w-10 h-10 rounded-2xl bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400">
          <UploadCloud class="w-5 h-5" />
        </div>
        <div class="text-xs font-black text-slate-700 dark:text-slate-200">
          <span>{{ placeholder || 'اضغط هنا لرفع ملف أو اسحبه إلى هنا' }}</span>
        </div>
        <p v-if="hint" class="text-[10px] font-medium text-slate-400 dark:text-slate-500">
          {{ hint }}
        </p>
      </div>
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
import { ref, computed } from 'vue';
import { UploadCloud, X, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
  label: { type: String, default: '' },
  accept: { type: String, default: 'image/*' },
  multiple: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: [String, Array], default: null },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' }
});

const emit = defineEmits(['change', 'clear']);

const model = defineModel({
  type: [Object, File, String, Array],
  default: null
});

const fileInputRef = ref(null);
const isDragging = ref(false);
const previewUrl = ref(typeof model.value === 'string' ? model.value : null);

const hasError = computed(() => {
  if (Array.isArray(props.error)) return props.error.length > 0;
  return !!props.error;
});

const errorMessage = computed(() => {
  if (Array.isArray(props.error)) return props.error[0];
  return props.error;
});

const triggerFileInput = () => {
  if (!props.disabled) fileInputRef.value?.click();
};

const handleFileChange = (e) => {
  const files = e.target.files;
  if (!files || files.length === 0) return;
  processFile(files[0]);
};

const handleDrop = (e) => {
  isDragging.value = false;
  if (props.disabled) return;
  const files = e.dataTransfer.files;
  if (!files || files.length === 0) return;
  processFile(files[0]);
};

const processFile = (file) => {
  model.value = file;
  if (file.type.startsWith('image/')) {
    previewUrl.value = URL.createObjectURL(file);
  } else {
    previewUrl.value = null;
  }
  emit('change', file);
};

const clearFile = () => {
  model.value = null;
  previewUrl.value = null;
  if (fileInputRef.value) fileInputRef.value.value = '';
  emit('clear');
};
</script>