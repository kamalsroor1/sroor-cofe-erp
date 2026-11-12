<script setup>
import { Moon, Sun } from 'lucide-vue-next';
import BrandMark from '../../Components/Brand/BrandMark.vue';
import LanguageSwitcher from '../../Components/Common/LanguageSwitcher.vue';
import ChangePasswordForm from '../../Components/Auth/ChangePasswordForm.vue';
import ChangePasswordSkeleton from '../../Components/Auth/ChangePasswordSkeleton.vue';
import { usePasswordChange } from '../../Composables/Auth/usePasswordChange';
import { useAppConfigStore } from '../../stores/appConfig';
import { useAuthStore } from '../../stores/auth';

const appConfigStore = useAppConfigStore();
const authStore = useAuthStore();
const { form, errors, formError, isSubmitting, isVerifying, submit, logout } = usePasswordChange();

const toggleTheme = () => appConfigStore.setTheme(appConfigStore.theme === 'dark' ? 'light' : 'dark');
</script>

<template>
  <div
    class="relative flex min-h-screen items-center justify-center overflow-clip bg-slate-50 p-4 font-sans transition-colors duration-300 sm:p-6 dark:bg-slate-900"
    data-testid="change-password-screen"
  >
    <div class="absolute end-4 top-4 z-20 flex items-center gap-2">
      <LanguageSwitcher class="rounded-2xl shadow-md backdrop-blur-md" />
      <button
        type="button"
        data-testid="theme-toggle"
        class="flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white/90 px-3 text-xs font-bold text-slate-700 shadow-md backdrop-blur-md transition dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-200"
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
        <BrandMark :size="64" class="mx-auto" />
        <h1 class="font-tajawal text-xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('auth.change_password.title') }}
        </h1>
        <p class="font-tajawal text-xs font-bold leading-relaxed text-slate-500 dark:text-slate-400">
          {{ $t('auth.change_password.subtitle', { name: authStore.userName }) }}
        </p>
      </header>

      <ChangePasswordSkeleton v-if="isVerifying" />
      <ChangePasswordForm
        v-else
        v-model:form="form"
        :errors="errors"
        :form-error="formError"
        :is-submitting="isSubmitting"
        @submit="submit"
        @logout="logout"
      />
    </main>
  </div>
</template>
