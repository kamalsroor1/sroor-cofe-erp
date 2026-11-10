<script setup>
import { Headset } from 'lucide-vue-next';

defineProps({
  support: { type: Object, default: () => ({}) },
});
</script>

<template>
  <p
    v-if="support?.phone || support?.email"
    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-bold text-slate-500 dark:text-slate-400"
  >
    <Headset class="w-4 h-4 shrink-0" />
    <span>{{ $t('subscription.billing.requests.support') }}</span>
    <span v-if="support.phone" dir="ltr">{{ $t('subscription.billing.requests.phone') }}: {{ support.phone }}</span>
    <span v-if="support.email" dir="ltr">{{ $t('subscription.billing.requests.email') }}: {{ support.email }}</span>
  </p>
</template>
