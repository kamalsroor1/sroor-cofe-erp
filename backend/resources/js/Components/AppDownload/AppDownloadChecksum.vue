<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { Check, Copy, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
  checksum: { type: String, required: true },
  /** Shell command that prints the file's SHA-256. */
  verifyCommand: { type: String, required: true },
});

const copied = ref(false);
let timer = null;

const copyChecksum = async () => {
  try {
    await navigator.clipboard.writeText(props.checksum);
    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch {
    copied.value = false;
  }
};

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
  <div class="flex flex-col gap-2 rounded-xl bg-mk-bg p-3.5 text-[13px]">
    <div class="flex items-center gap-2">
      <ShieldCheck class="size-4 shrink-0 text-mk-primary" aria-hidden="true" />
      <span class="flex-1 font-bold text-mk-muted">{{ $t('app_update.download_page.checksum_title') }}</span>
      <button
        type="button"
        class="-my-1.5 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl text-mk-primary transition hover:bg-mk-surface active:scale-90 focus-visible:outline-2 focus-visible:outline-mk-primary"
        :aria-label="$t('app_update.download_page.copy_checksum')"
        @click="copyChecksum"
      >
        <Check v-if="copied" class="size-4" />
        <Copy v-else class="size-4" />
      </button>
    </div>
    <code dir="ltr" class="block font-mono text-xs leading-relaxed break-all text-mk-ink">{{ checksum }}</code>
    <span class="sr-only" aria-live="polite">{{ copied ? $t('marketing.download.checksum_copied') : '' }}</span>
    <p class="leading-relaxed text-mk-faint">{{ $t('app_update.download_page.checksum_hint') }}</p>
    <code dir="ltr" class="block font-mono text-xs text-mk-muted">{{ verifyCommand }}</code>
  </div>
</template>
