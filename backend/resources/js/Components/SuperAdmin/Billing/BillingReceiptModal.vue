<script setup>
import { ExternalLink, RefreshCw } from 'lucide-vue-next';
import AppModal from '../../Common/AppModal.vue';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';

defineProps({
  show: { type: Boolean, default: false },
  subtitle: { type: String, default: '' },
  receipt: { type: Object, required: true },
});

defineEmits(['close', 'retry']);
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('billing.console.receipt.title')"
    :subtitle="subtitle"
    max-width="3xl"
    @close="$emit('close')"
  >
    <div class="space-y-4 font-tajawal">
      <div
        v-if="receipt.state === 'loading'"
        role="status"
        aria-busy="true"
        :aria-label="$t('billing.console.receipt.loading')"
      >
        <Skeleton height="h-80" rounded="rounded-2xl" />
      </div>

      <div
        v-else-if="receipt.state === 'error'"
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 p-4"
      >
        <p class="text-xs font-bold text-rose-700 dark:text-rose-300">
          {{ $t('billing.console.receipt.load_failed') }}
        </p>
        <button
          type="button"
          class="min-h-[44px] px-4 rounded-xl border border-rose-300 dark:border-rose-800 bg-white dark:bg-slate-900 text-rose-700 dark:text-rose-300 text-xs font-black inline-flex items-center gap-2 transition active:scale-[0.98]"
          @click="$emit('retry')"
        >
          <RefreshCw class="w-4 h-4" />
          <span>{{ $t('billing.console.receipt.retry') }}</span>
        </button>
      </div>

      <div
        v-else-if="receipt.state === 'ready'"
        class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950 overflow-hidden"
      >
        <img
          v-if="receipt.kind === 'image'"
          :src="receipt.objectUrl"
          :alt="$t('billing.console.receipt.image_alt')"
          class="block max-h-[70vh] w-full object-contain"
        />
        <iframe
          v-else
          :src="receipt.objectUrl"
          :title="$t('billing.console.receipt.frame_title')"
          class="block h-[70vh] w-full border-0"
        ></iframe>
      </div>

      <a
        v-if="receipt.state === 'ready' && receipt.objectUrl"
        :href="receipt.objectUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="min-h-[44px] px-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs font-black inline-flex items-center gap-2 transition active:scale-[0.98]"
      >
        <ExternalLink class="w-4 h-4" />
        <span>{{ $t('billing.console.receipt.open_new_tab') }}</span>
      </a>
    </div>
  </AppModal>
</template>
