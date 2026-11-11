<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Menu } from 'lucide-vue-next';
import LandingIndustriesMenu from './LandingIndustriesMenu.vue';
import LandingLocaleToggle from './LandingLocaleToggle.vue';
import LandingLogo from './LandingLogo.vue';
import LandingMobileMenu from './LandingMobileMenu.vue';
import LandingThemeToggle from './LandingThemeToggle.vue';
import MarketingButton from './MarketingButton.vue';
import { useLandingNav } from '../../Composables/useLandingNav';
import { useMediaQuery } from '../../Composables/useMediaQuery';

defineProps({
  brandName: { type: String, default: '' },
});

const route = useRoute();
const { links, activeKey } = useLandingNav();
const isDesktop = useMediaQuery('(min-width: 1024px)');

const isMenuOpen = ref(false);
const menuButton = ref(null);

const closeMenu = ({ restoreFocus = false } = {}) => {
  isMenuOpen.value = false;
  if (restoreFocus) menuButton.value?.focus();
};

watch(
  () => route.fullPath,
  () => closeMenu()
);
watch(isDesktop, (desktop) => {
  if (desktop) closeMenu();
});
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-mk-line bg-mk-surface/95 backdrop-blur-md">
    <div class="mx-auto flex h-[68px] max-w-[1248px] items-center justify-between gap-4 px-5 sm:px-6 lg:h-[76px]">
      <div class="flex min-w-0 items-center gap-8 xl:gap-14">
        <router-link
          :to="{ name: 'landing.home' }"
          :aria-label="$t('landing.nav.home')"
          class="flex min-h-11 min-w-0 items-center rounded-lg focus-visible:outline-2 focus-visible:outline-mk-primary"
        >
          <LandingLogo :name="brandName" :size="isDesktop ? 36 : 30" />
        </router-link>

        <nav class="hidden items-center gap-5 lg:flex xl:gap-[30px]" :aria-label="$t('landing.nav.main')">
          <template v-for="link in links" :key="link.key">
            <LandingIndustriesMenu v-if="link.children" :active="activeKey === link.key" />
            <router-link
              v-else
              :to="link.to"
              class="inline-flex min-h-11 items-center rounded-lg text-base whitespace-nowrap transition hover:text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary"
              :class="activeKey === link.key ? 'font-bold text-mk-ink' : 'font-medium text-mk-muted'"
              :aria-current="activeKey === link.key ? 'page' : undefined"
            >
              {{ $t(link.labelKey) }}
            </router-link>
          </template>
        </nav>
      </div>

      <div class="flex shrink-0 items-center gap-2 lg:gap-3 xl:gap-5">
        <router-link
          :to="{ name: 'login' }"
          class="hidden min-h-11 items-center rounded-lg px-1 text-base font-bold whitespace-nowrap text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary lg:inline-flex"
        >
          {{ $t('landing.nav.login') }}
        </router-link>
        <div class="hidden items-center gap-2 lg:flex">
          <LandingThemeToggle />
          <LandingLocaleToggle />
        </div>
        <div class="hidden sm:block">
          <MarketingButton to="/signup" variant="primary" size="md">{{ $t('landing.request_trial') }}</MarketingButton>
        </div>
        <button
          ref="menuButton"
          type="button"
          class="flex size-11 cursor-pointer items-center justify-center rounded-xl border border-mk-line bg-mk-surface text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary active:scale-95 lg:hidden"
          :aria-expanded="isMenuOpen"
          aria-controls="landing-mobile-menu"
          :aria-label="$t('landing.nav.open_menu')"
          @click="isMenuOpen = true"
        >
          <Menu class="size-[22px]" aria-hidden="true" />
        </button>
      </div>
    </div>
  </header>
  <LandingMobileMenu :open="isMenuOpen" :brand-name="brandName" @close="closeMenu" />
</template>
