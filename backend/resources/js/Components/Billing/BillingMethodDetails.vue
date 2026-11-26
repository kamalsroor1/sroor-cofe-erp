<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { Check, Copy, Info } from 'lucide-vue-next';
import { copyText } from '@/helpers/copyText';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps({
  // One entry of `payment_methods`: { key, label, details: [{ key, label, value, copyable }], note }.
  method: { type: Object, required: true },
  // Preview mode (super-admin settings): the same rows, copy buttons inert.
  preview: { type: Boolean, default: false },
});

const { t } = useTrans();
const copiedKey = ref('');
const announce = ref('');
let timer = null;

const isCopyable = (row) => row.copyable !== false;

const copy = async (row) => {
  if (props.preview) return;
  if (!(await copyText(row.value))) return;
  copiedKey.value = row.key;
  announce.value = t('subscription.billing.pay.copied_announce', { label: row.label });
  clearTimeout(timer);
  timer = setTimeout(() => {
    copiedKey.value = '';
  }, 2000);
};

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
  <div class="space-y-2">
    <ul class="space-y-2">
      <li
        v-for="row in method.details"
        :key="row.key"
        class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 dark:bg-slate-800 p-2 ps-3 transition"
        :class="copiedKey === row.key ? 'ring-1 ring-emerald-500' : ''"
      >
        <div class="min-w-0">
          <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ row.label }}</p>
          <p
            class="text-base font-black break-all"
            :class="
              isCopyable(row) ? 'text-slate-950 dark:text-white select-all' : 'text-slate-700 dark:text-slate-300'
            "
            dir="ltr"
          >
            {{ row.value }}
          </p>
        </div>
        <button
          v-if="isCopyable(row)"
          type="button"
          class="min-w-[76px] min-h-11 shrink-0 rounded-lg px-2 inline-flex items-center justify-center gap-1 text-sm font-bold cursor-pointer transition active:scale-95"
          :class="copiedKey === row.key ? 'bg-emerald-500 text-emerald-950' : 'bg-theme-primary text-slate-950'"
          :aria-label="$t('subscription.billing.pay.copy_aria', { label: row.label })"
          @click="copy(row)"
        >
          <Check v-if="copiedKey === row.key" class="w-4 h-4" />
          <Copy v-else class="w-4 h-4" />
          <span>{{
            copiedKey === row.key ? $t('subscription.billing.pay.copied') : $t('subscription.billing.pay.copy')
          }}</span>
        </button>
      </li>
    </ul>

    <div
      v-if="method.note"
      class="flex items-start gap-2 rounded-xl border border-amber-400 bg-amber-50 dark:bg-amber-500/10 p-3 text-sm font-bold text-amber-900 dark:text-amber-200"
    >
      <Info class="w-4 h-4 shrink-0 mt-0.5" />
      <p class="min-w-0 whitespace-pre-line">{{ method.note }}</p>
    </div>

    <p class="sr-only" role="status" aria-live="polite">{{ announce }}</p>
  </div>
</template>
