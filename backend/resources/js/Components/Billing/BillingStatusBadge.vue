<script setup>
import { toneClass } from '@/helpers/billing';

defineProps({
  label: { type: String, required: true },
  tone: { type: String, default: 'neutral' },
});
</script>

<template>
  <span
    class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black leading-none whitespace-nowrap"
    :class="toneClass(tone)"
  >
    {{ label }}
  </span>
</template>
