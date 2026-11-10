<script setup>
import BrandMark from '../Brand/BrandMark.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  brandName: { type: String, default: '' },
});
</script>

<template>
  <main
    class="min-h-0 flex-1 overflow-y-auto bg-slate-50 font-tajawal text-slate-900 dark:bg-slate-950 dark:text-slate-100"
  >
    <header
      class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90"
    >
      <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-between gap-3 px-4 py-2 sm:px-6">
        <router-link to="/welcome" class="flex min-h-11 items-center gap-2.5">
          <BrandMark :size="40" decorative class="shrink-0" />
          <span class="font-brand truncate text-base font-semibold text-slate-900 dark:text-white">{{
            brandName
          }}</span>
        </router-link>

        <nav class="flex items-center gap-1.5 sm:gap-2" :aria-label="brandName">
          <router-link
            to="/pricing"
            class="flex min-h-11 items-center rounded-xl px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            {{ $t('landing.nav.pricing') }}
          </router-link>
          <BaseButton to="/signup" variant="primary" size="md" class="hidden sm:inline-flex">
            {{ $t('landing.request_trial') }}
          </BaseButton>
          <BaseButton to="/signup" variant="primary" size="md" class="sm:hidden">
            {{ $t('landing.nav.signup') }}
          </BaseButton>
        </nav>
      </div>
    </header>

    <slot />

    <footer class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <slot name="footer" />
      </div>
    </footer>
  </main>
</template>
