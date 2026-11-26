<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Camera, FileText, Trash2, UploadCloud } from 'lucide-vue-next';
import { RECEIPT_ACCEPT } from '@/helpers/billing';

const props = defineProps({
  error: { type: String, default: '' },
  // Drop-zone headline; the method flow passes "after the transfer: ...".
  title: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const file = defineModel({ type: [File, null], default: null });
const fileInput = ref(null);
const cameraInput = ref(null);
const previewUrl = ref('');

const isImage = computed(() => !!file.value && file.value.type.startsWith('image/'));
// The camera button is offered on touch devices only; a desktop browser has the file picker.
const hasCamera = typeof window !== 'undefined' && !!window.matchMedia?.('(pointer: coarse)').matches;

const revoke = () => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = '';
};

watch(file, (next) => {
  revoke();
  if (next && next.type.startsWith('image/')) previewUrl.value = URL.createObjectURL(next);
});
onBeforeUnmount(revoke);

const onPick = (event) => {
  file.value = event.target.files?.[0] || null;
  event.target.value = '';
};
</script>

<template>
  <div class="space-y-1.5">
    <div
      v-if="file"
      class="flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 p-2.5"
    >
      <img v-if="isImage && previewUrl" :src="previewUrl" alt="" class="w-14 h-14 rounded-xl object-cover shrink-0" />
      <FileText v-else class="w-10 h-10 text-slate-400 shrink-0" />
      <span class="flex-1 min-w-0 text-xs font-bold text-slate-800 dark:text-slate-200 truncate" dir="ltr">
        {{ file.name }}
      </span>
      <button
        type="button"
        class="min-h-11 px-3 rounded-xl text-xs font-black text-theme-primary hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer disabled:opacity-50"
        :disabled="disabled"
        @click="fileInput.click()"
      >
        {{ $t('subscription.billing.pay.change_file') }}
      </button>
      <button
        type="button"
        class="min-w-11 min-h-11 rounded-xl flex items-center justify-center text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer disabled:opacity-50"
        :aria-label="$t('subscription.billing.pay.remove_receipt')"
        :disabled="disabled"
        @click="file = null"
      >
        <Trash2 class="w-4 h-4" />
      </button>
    </div>

    <div v-else class="flex flex-col sm:flex-row gap-2">
      <button
        type="button"
        class="flex-1 min-h-28 rounded-2xl border-2 border-dashed px-4 py-4 flex flex-col items-center justify-center gap-1 text-center cursor-pointer transition bg-slate-50 dark:bg-slate-800/50 disabled:opacity-50"
        :class="props.error ? 'border-rose-500' : 'border-slate-300 dark:border-slate-600'"
        :disabled="disabled"
        @click="fileInput.click()"
      >
        <UploadCloud class="w-6 h-6 text-slate-400" />
        <span class="text-sm font-black text-slate-700 dark:text-slate-200">
          {{ title || $t('subscription.billing.pay.drop_title') }}
        </span>
        <span class="text-xs font-medium text-slate-500 dark:text-slate-400" dir="auto">
          {{ $t('subscription.billing.pay.drop_hint') }}
        </span>
      </button>
      <button
        v-if="hasCamera"
        type="button"
        class="min-h-11 rounded-2xl border border-slate-300 dark:border-slate-600 px-4 inline-flex items-center justify-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-200 cursor-pointer disabled:opacity-50"
        :disabled="disabled"
        @click="cameraInput.click()"
      >
        <Camera class="w-4 h-4" />
        {{ $t('subscription.billing.pay.take_photo') }}
      </button>
    </div>

    <input ref="fileInput" type="file" class="sr-only" tabindex="-1" :accept="RECEIPT_ACCEPT" @change="onPick" />
    <input
      ref="cameraInput"
      type="file"
      class="sr-only"
      tabindex="-1"
      accept="image/*"
      capture="environment"
      @change="onPick"
    />
    <p v-if="props.error" class="text-xs font-bold text-rose-600 dark:text-rose-400" role="alert">{{ props.error }}</p>
  </div>
</template>
