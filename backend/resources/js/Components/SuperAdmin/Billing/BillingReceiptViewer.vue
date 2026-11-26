<script setup>
import { computed, ref, watch } from 'vue';
import { ExternalLink, RefreshCw, RotateCw, ZoomIn, ZoomOut } from 'lucide-vue-next';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';

const props = defineProps({
  receipt: { type: Object, required: true },
  hasReceipt: { type: Boolean, default: true },
  resetKey: { type: [Number, String], default: null },
});

defineEmits(['retry']);

const ZOOM_STEP = 0.25;
const zoom = ref(1);
const turns = ref(0);

watch(
  () => [props.resetKey, props.receipt.objectUrl],
  () => {
    zoom.value = 1;
    turns.value = 0;
  }
);

const imageStyle = computed(() => ({ transform: `rotate(${turns.value * 90}deg) scale(${zoom.value})` }));
const zoomBy = (delta) => {
  zoom.value = Math.min(3, Math.max(0.5, zoom.value + delta));
};

const toolButton =
  'min-h-11 min-w-11 inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-300 bg-slate-50 px-3 text-xs font-black text-slate-800 transition active:scale-[0.98] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200';
</script>

<template>
  <section class="space-y-2" :aria-label="$t('billing.console.verify.receipt_title')">
    <p
      v-if="!hasReceipt"
      class="rounded-xl border border-slate-300 bg-slate-100 p-3 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
    >
      {{ $t('billing.console.queue.no_receipt') }}
    </p>

    <div
      v-else-if="receipt.state === 'loading' || receipt.state === 'idle'"
      role="status"
      aria-busy="true"
      :aria-label="$t('billing.console.receipt.loading')"
    >
      <Skeleton height="h-80" rounded="rounded-2xl" />
    </div>

    <div
      v-else-if="receipt.state === 'error'"
      role="alert"
      class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rose-500 bg-rose-50 p-4 dark:bg-rose-500/10"
    >
      <p class="text-xs font-bold text-rose-900 dark:text-rose-200">{{ $t('billing.console.receipt.load_failed') }}</p>
      <button type="button" :class="toolButton" @click="$emit('retry')">
        <RefreshCw class="h-4 w-4" aria-hidden="true" />
        <span>{{ $t('billing.console.receipt.retry') }}</span>
      </button>
    </div>

    <template v-else>
      <div class="flex flex-wrap gap-2">
        <template v-if="receipt.kind === 'image'">
          <button
            type="button"
            :class="toolButton"
            :aria-label="$t('billing.console.verify.zoom_in')"
            @click="zoomBy(ZOOM_STEP)"
          >
            <ZoomIn class="h-4 w-4" aria-hidden="true" />
          </button>
          <button
            type="button"
            :class="toolButton"
            :aria-label="$t('billing.console.verify.zoom_out')"
            @click="zoomBy(-ZOOM_STEP)"
          >
            <ZoomOut class="h-4 w-4" aria-hidden="true" />
          </button>
          <button
            type="button"
            :class="toolButton"
            :aria-label="$t('billing.console.verify.rotate')"
            @click="turns += 1"
          >
            <RotateCw class="h-4 w-4" aria-hidden="true" />
          </button>
        </template>
        <a :href="receipt.objectUrl" target="_blank" rel="noopener noreferrer" :class="toolButton">
          <ExternalLink class="h-4 w-4" aria-hidden="true" />
          <span>{{ $t('billing.console.verify.open_new_tab') }}</span>
        </a>
      </div>

      <div
        class="max-h-[70vh] overflow-auto rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-950"
      >
        <img
          v-if="receipt.kind === 'image'"
          :src="receipt.objectUrl"
          :alt="$t('billing.console.receipt.image_alt')"
          class="mx-auto block w-full origin-center object-contain transition-transform"
          :style="imageStyle"
        />
        <iframe
          v-else
          :src="receipt.objectUrl"
          :title="$t('billing.console.receipt.frame_title')"
          class="block h-[70vh] w-full border-0"
        ></iframe>
      </div>
    </template>
  </section>
</template>
