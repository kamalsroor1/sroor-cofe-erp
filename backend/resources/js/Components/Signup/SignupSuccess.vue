<script setup>
import { Check } from 'lucide-vue-next';
import MarketingButton from '../Landing/MarketingButton.vue';

defineProps({
  message: { type: String, default: '' },
});
</script>

<template>
  <div
    role="status"
    class="mx-auto flex w-full max-w-[560px] flex-col items-center gap-4 rounded-3xl border border-mk-line bg-mk-surface p-7 text-center sm:p-10"
  >
    <span class="inline-flex size-[72px] items-center justify-center rounded-full bg-mk-primary text-mk-primary-ink">
      <Check class="size-9" stroke-width="3" aria-hidden="true" />
    </span>
    <h1 class="text-[26px] font-bold text-mk-ink sm:text-[30px]">{{ $t('signup.form.success_title') }}</h1>
    <p class="text-base leading-[1.6] text-mk-muted sm:text-[17px]">{{ message }}</p>
    <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
      <MarketingButton to="/welcome" variant="primary">{{ $t('signup.form.back_home') }}</MarketingButton>
      <MarketingButton to="/pricing" variant="outline">{{ $t('signup.form.view_pricing') }}</MarketingButton>
    </div>
  </div>
</template>
