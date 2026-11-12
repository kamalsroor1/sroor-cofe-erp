<template>
  <div>
    <span :class="valueClass">{{ value }}</span>
    <span
      v-if="detail"
      class="block text-[10px] font-normal font-tajawal text-rose-500 dark:text-rose-400 leading-tight"
      >{{ detail }}</span
    >
  </div>
</template>

<script setup>
defineProps({
  value: { type: String, required: true },
  detail: { type: String, default: '' },
  valueClass: { type: String, default: 'font-mono font-bold text-slate-900 dark:text-white' },
});
</script>
