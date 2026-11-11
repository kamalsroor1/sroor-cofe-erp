<script setup>
import { Info } from 'lucide-vue-next';
</script>

<template>
  <div class="flex justify-center bg-mk-bg px-5 pt-8 pb-12 sm:px-6 lg:pt-10">
    <router-link
      :to="{ name: 'landing.pricing' }"
      class="inline-flex min-h-11 items-center gap-2 rounded-2xl border border-mk-line bg-mk-surface px-4 py-2 text-start sm:rounded-full text-[13px] font-medium text-mk-muted transition hover:bg-mk-surface-2 focus-visible:outline-2 focus-visible:outline-mk-primary sm:text-sm"
    >
      <Info class="size-4 shrink-0 text-mk-primary" aria-hidden="true" />
      {{ $t('landing.home.features.plan_note') }}
    </router-link>
  </div>
</template>
