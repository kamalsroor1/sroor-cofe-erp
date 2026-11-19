<script setup>
// SIGN-15: full-screen card shell of the shop's public auth pages (forgot / reset password):
// language + theme switchers, the shop logo (or brand mark), a title and a subtitle.
import { Moon, Sun } from 'lucide-vue-next';
import BrandMark from '../Brand/BrandMark.vue';
import LanguageSwitcher from '../Common/LanguageSwitcher.vue';
import { useAppConfigStore } from '../../stores/appConfig';

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});

const appConfigStore = useAppConfigStore();
const toggleTheme = () => appConfigStore.setTheme(appConfigStore.theme === 'dark' ? 'light' : 'dark');
</script>

<template>
  <div
    class="relative flex min-h-screen items-center justify-center overflow-clip bg-slate-50 p-4 font-sans transition-colors duration-300 sm:p-6 dark:bg-slate-900"
  >
    <div class="absolute end-4 top-4 z-20 flex items-center gap-2">
      <LanguageSwitcher class="rounded-2xl shadow-md backdrop-blur-md" />
      <button
        type="button"
        data-testid="theme-toggle"
        class="flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-2xl border border-slate-200 bg-white/90 px-3 text-xs font-bold text-slate-700 shadow-md backdrop-blur-md transition dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-200"
        :aria-label="appConfigStore.theme === 'dark' ? $t('nav.switch_to_light') : $t('nav.switch_to_dark')"
        @click="toggleTheme"
      >
        <Sun v-if="appConfigStore.theme === 'dark'" class="h-4 w-4 text-theme-primary" />
        <Moon v-else class="h-4 w-4 text-indigo-500" />
      </button>
    </div>

    <div class="pointer-events-none absolute start-0 top-0 h-96 w-96 rounded-full bg-theme-light blur-3xl"></div>

    <main
      class="relative z-10 w-full max-w-md space-y-6 rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-2xl shadow-slate-300/40 backdrop-blur-2xl sm:p-8 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none"
    >
      <header class="space-y-3 text-center">
        <img
          v-if="appConfigStore.companyLogo"
          :src="appConfigStore.companyLogo"
          :alt="appConfigStore.companyName"
          class="mx-auto h-16 w-16 object-contain"
        />
        <BrandMark v-else :size="64" class="mx-auto" />
        <h1 class="font-tajawal text-xl font-black tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
        <p v-if="subtitle" class="font-tajawal text-xs font-bold leading-relaxed text-slate-500 dark:text-slate-400">
          {{ subtitle }}
        </p>
      </header>

      <slot />

      <footer v-if="$slots.footer" class="text-center text-xs">
        <slot name="footer" />
      </footer>
    </main>
  </div>
</template>
