<script setup>
import { ShieldCheck } from 'lucide-vue-next';

defineProps({
  width: { type: Number, default: 700 },
  height: { type: Number, default: 470 },
});
</script>

<template>
  <div
    class="flex flex-col overflow-hidden rounded-2xl border border-mk-app-line bg-mk-app-bg mk-shadow-window"
    :style="{ width: `${width}px`, height: `${height}px` }"
  >
    <div
      class="flex h-[38px] shrink-0 items-center justify-between border-b border-mk-app-line bg-mk-app-surface px-3.5"
      dir="ltr"
    >
      <div class="flex gap-1.5">
        <span class="size-2.5 rounded-full bg-[#F26B5B]"></span>
        <span class="size-2.5 rounded-full bg-[#F6B93B]"></span>
        <span class="size-2.5 rounded-full bg-[#3FC28A]"></span>
      </div>
      <div class="flex h-6 items-center gap-1.5 rounded-full bg-mk-app-bg px-3 text-xs font-medium text-mk-app-muted">
        <ShieldCheck class="size-3" />
        {{ $t('landing.mock.url') }}
      </div>
      <span class="w-[52px]"></span>
    </div>
    <div class="min-h-0 flex-1 overflow-hidden">
      <slot />
    </div>
  </div>
</template>
