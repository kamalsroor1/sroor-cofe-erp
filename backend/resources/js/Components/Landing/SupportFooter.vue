<script setup>
import { Mail, Phone } from 'lucide-vue-next';

defineProps({
  brand: { type: Object, default: () => ({}) },
});
</script>

<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="space-y-1">
      <p class="text-sm font-black text-slate-900 dark:text-white">{{ brand.name }}</p>
      <p v-if="brand.subtitle" class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ brand.subtitle }}</p>
    </div>

    <div v-if="brand.support_phone || brand.support_email" class="flex flex-col gap-2 sm:flex-row sm:items-center">
      <span class="text-xs font-black text-slate-700 dark:text-slate-200">{{ $t('landing.home.support_title') }}</span>
      <a
        v-if="brand.support_phone"
        :href="`tel:${brand.support_phone}`"
        class="flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200"
        dir="ltr"
      >
        <Phone class="h-4 w-4" style="color: var(--color-primary)" />
        <span>{{ brand.support_phone }}</span>
      </a>
      <a
        v-if="brand.support_email"
        :href="`mailto:${brand.support_email}`"
        class="flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200"
        dir="ltr"
      >
        <Mail class="h-4 w-4" style="color: var(--color-primary)" />
        <span>{{ brand.support_email }}</span>
      </a>
    </div>
  </div>
</template>
