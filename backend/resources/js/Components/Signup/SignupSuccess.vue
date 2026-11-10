<script setup>
import { CheckCircle2 } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  message: { type: String, default: '' },
});
</script>

<template>
  <div
    role="status"
    class="space-y-4 rounded-3xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"
  >
    <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-emerald-500/10 text-emerald-500">
      <CheckCircle2 class="h-9 w-9" />
    </span>
    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $t('signup.form.success_title') }}</h2>
    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ message }}</p>
    <div class="flex flex-col justify-center gap-3 sm:flex-row">
      <BaseButton to="/welcome" variant="primary" size="md">{{ $t('signup.form.back_home') }}</BaseButton>
      <BaseButton to="/pricing" variant="outline" size="md">{{ $t('signup.form.view_pricing') }}</BaseButton>
    </div>
  </div>
</template>
