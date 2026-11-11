<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Menu, X } from 'lucide-vue-next';
import BrandMark from '../Brand/BrandMark.vue';
import BaseButton from '../Common/BaseButton.vue';
import LanguageSwitcher from '../Common/LanguageSwitcher.vue';
import LandingNavLinks from './LandingNavLinks.vue';
import LandingThemeToggle from './LandingThemeToggle.vue';

defineProps({
  brandName: { type: String, default: '' },
});

const route = useRoute();
const isMenuOpen = ref(false);
const menuButton = ref(null);

const closeMenu = ({ restoreFocus = false } = {}) => {
  isMenuOpen.value = false;
  if (restoreFocus) menuButton.value?.focus();
};

const onKeydown = (event) => {
  if (event.key === 'Escape') closeMenu({ restoreFocus: true });
};

watch(isMenuOpen, (open) => {
  if (open) document.addEventListener('keydown', onKeydown);
  else document.removeEventListener('keydown', onKeydown);
});
watch(
  () => route.fullPath,
  () => closeMenu()
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
  <main
    class="min-h-0 flex-1 overflow-y-auto bg-slate-50 font-tajawal text-slate-900 dark:bg-slate-950 dark:text-slate-100"
  >
    <header
      class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90"
    >
      <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-between gap-3 px-4 py-2 sm:px-6">
        <router-link
          :to="{ name: 'landing.home' }"
          :aria-label="brandName || $t('landing.nav.home')"
          class="flex min-h-11 min-w-0 items-center gap-2.5"
        >
          <BrandMark :size="40" decorative class="shrink-0" />
          <span class="font-brand truncate text-base font-semibold text-slate-900 dark:text-white">{{
            brandName
          }}</span>
        </router-link>

        <nav class="hidden items-center gap-1 lg:flex" :aria-label="$t('landing.nav.main')">
          <LandingNavLinks />
        </nav>

        <div class="flex shrink-0 items-center gap-2">
          <div class="hidden items-center gap-2 lg:flex">
            <LanguageSwitcher />
            <LandingThemeToggle />
          </div>
          <div class="hidden sm:block">
            <BaseButton to="/signup" variant="primary" size="md">{{ $t('landing.request_trial') }}</BaseButton>
          </div>
          <button
            ref="menuButton"
            type="button"
            class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-slate-100 text-slate-700 transition active:scale-95 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 lg:hidden"
            :aria-expanded="isMenuOpen"
            aria-controls="landing-mobile-menu"
            :aria-label="isMenuOpen ? $t('landing.nav.close_menu') : $t('landing.nav.open_menu')"
            @click="isMenuOpen = !isMenuOpen"
          >
            <X v-if="isMenuOpen" class="h-5 w-5" />
            <Menu v-else class="h-5 w-5" />
          </button>
        </div>
      </div>

      <div
        v-show="isMenuOpen"
        id="landing-mobile-menu"
        class="border-t border-slate-200 bg-white px-4 pb-4 pt-2 dark:border-slate-800 dark:bg-slate-900 lg:hidden"
      >
        <nav class="flex flex-col gap-1" :aria-label="$t('landing.nav.main')">
          <LandingNavLinks variant="menu" @navigate="closeMenu()" />
        </nav>
        <div class="mt-3 flex items-center gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
          <LanguageSwitcher />
          <LandingThemeToggle />
        </div>
        <div class="mt-3 sm:hidden">
          <BaseButton to="/signup" variant="primary" size="lg" full-width>{{ $t('landing.request_trial') }}</BaseButton>
        </div>
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
