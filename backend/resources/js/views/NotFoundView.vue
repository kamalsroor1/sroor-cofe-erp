<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowRight, FileQuestion, Home } from 'lucide-vue-next';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { landingPathFor } from '../helpers/landingPath';
import BaseButton from '../Components/Common/BaseButton.vue';

const router = useRouter();
const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();

const homePath = computed(() => (authStore.isAuthenticated ? landingPathFor(authStore, appConfigStore) : '/'));
const canGoBack = computed(() => typeof window !== 'undefined' && window.history.length > 1);

const goBack = () => {
  if (canGoBack.value) {
    router.back();
  } else {
    router.push(homePath.value);
  }
};
</script>

<template>
  <div
    data-testid="not-found-view"
    class="min-h-full flex-1 flex items-center justify-center p-4 sm:p-6 font-tajawal select-none"
  >
    <section
      class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/90 p-6 sm:p-8 text-center shadow-xl space-y-5"
    >
      <div
        class="mx-auto w-16 h-16 sm:w-20 sm:h-20 rounded-3xl flex items-center justify-center border"
        :style="{
          backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.12))',
          borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.3))',
          color: 'var(--color-primary, #f59e0b)',
        }"
      >
        <FileQuestion class="w-8 h-8 sm:w-10 sm:h-10 stroke-[1.5]" aria-hidden="true" />
      </div>

      <div class="space-y-2">
        <span
          class="inline-block px-3 py-1 rounded-full text-xs font-mono font-black border"
          :style="{
            backgroundColor: 'var(--color-primary-light, rgba(245, 158, 11, 0.12))',
            borderColor: 'var(--color-primary-border, rgba(245, 158, 11, 0.3))',
            color: 'var(--color-primary, #f59e0b)',
          }"
        >
          {{ $t('common.not_found.code') }}
        </span>
        <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
          {{ $t('common.not_found.title') }}
        </h1>
        <p class="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 leading-relaxed">
          {{ $t('common.not_found.description') }}
        </p>
      </div>

      <div class="pt-1 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-2.5">
        <router-link
          :to="homePath"
          data-testid="not-found-home-link"
          class="min-h-[44px] px-5 py-2.5 rounded-2xl text-white font-black text-xs sm:text-sm inline-flex items-center justify-center gap-2 shadow-lg transition active:scale-95 hover:brightness-105"
          :style="{ backgroundColor: 'var(--color-primary, #f59e0b)' }"
        >
          <Home class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span>{{ $t('common.not_found.back_home') }}</span>
        </router-link>

        <BaseButton
          v-if="canGoBack"
          variant="secondary"
          size="md"
          data-testid="not-found-back-button"
          class="min-h-[44px]"
          @click="goBack"
        >
          <ArrowRight class="w-4 h-4 ltr:rotate-180 shrink-0" aria-hidden="true" />
          <span>{{ $t('common.not_found.go_back') }}</span>
        </BaseButton>
      </div>
    </section>
  </div>
</template>
