<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { FileText, Trash2, UploadCloud } from 'lucide-vue-next';
import { RECEIPT_ACCEPT } from '@/helpers/billing';

const props = defineProps({
  error: { type: String, default: '' },
});

const file = defineModel({ type: [File, null], default: null });
const input = ref(null);
const previewUrl = ref('');

const isImage = computed(() => !!file.value && file.value.type.startsWith('image/'));

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
    <label class="block text-xs font-black text-slate-700 dark:text-slate-200">
      {{ $t('subscription.billing.pay.receipt') }}
      <span class="text-rose-500">*</span>
    </label>

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
        class="min-w-[44px] min-h-[44px] rounded-xl flex items-center justify-center text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer"
        :aria-label="$t('subscription.billing.pay.remove_receipt')"
        @click="file = null"
      >
        <Trash2 class="w-4 h-4" />
      </button>
    </div>

    <button
      v-else
      type="button"
      class="w-full min-h-[88px] rounded-2xl border-2 border-dashed px-4 py-4 flex flex-col items-center justify-center gap-1 text-center cursor-pointer transition bg-slate-50 dark:bg-slate-800/50"
      :class="props.error ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700'"
      @click="input.click()"
    >
      <UploadCloud class="w-6 h-6 text-slate-400" />
      <span class="text-xs font-black text-slate-700 dark:text-slate-200">
        {{ $t('subscription.billing.pay.choose_receipt') }}
      </span>
      <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
        {{ $t('subscription.billing.pay.receipt_hint') }}
      </span>
    </button>

    <input ref="input" type="file" class="sr-only" :accept="RECEIPT_ACCEPT" @change="onPick" />
    <p v-if="props.error" class="text-xs font-bold text-rose-500 dark:text-rose-400">{{ props.error }}</p>
  </div>
</template>
