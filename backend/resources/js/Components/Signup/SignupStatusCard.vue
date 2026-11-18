<script setup>
import { computed } from 'vue';
import { AlertTriangle, Check, Clock } from 'lucide-vue-next';

const props = defineProps({
  tone: { type: String, default: 'success', validator: (value) => ['success', 'info', 'danger'].includes(value) },
  title: { type: String, required: true },
  body: { type: String, default: '' },
});

const ICONS = { success: Check, info: Clock, danger: AlertTriangle };
const TONES = {
  success: 'bg-mk-primary text-mk-primary-ink',
  info: 'bg-mk-surface-2 text-mk-ink',
  danger: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
};
const icon = computed(() => ICONS[props.tone]);
</script>

<template>
  <div
    :role="tone === 'danger' ? 'alert' : 'status'"
    class="mx-auto flex w-full max-w-[560px] flex-col items-center gap-4 rounded-3xl border border-mk-line bg-mk-surface p-7 text-center mk-shadow-card sm:p-10"
  >
    <span class="inline-flex size-[72px] items-center justify-center rounded-full" :class="TONES[tone]">
      <component :is="icon" class="size-9" stroke-width="2.5" aria-hidden="true" />
    </span>
    <h1 class="text-[26px] font-bold text-mk-ink sm:text-[30px]">{{ title }}</h1>
    <p v-if="body" class="text-base leading-[1.6] text-mk-muted sm:text-[17px]">{{ body }}</p>
    <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
      <slot />
    </div>
  </div>
</template>
