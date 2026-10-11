<script setup>
import { computed, onMounted, watchEffect } from 'vue';
import { AlertTriangle, PackageOpen, RefreshCw } from 'lucide-vue-next';
import AppDownloadCard from '../../Components/AppDownload/AppDownloadCard.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import CardSkeleton from '../../Components/Common/Skeletons/CardSkeleton.vue';
import { useAppDownloads } from '../../Composables/useAppUpdate';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();
const { releases, isLoading, hasError, load, currentPlatform } = useAppDownloads();

// The visitor's own platform first.
const sortedReleases = computed(() =>
  [...releases.value].sort((a, b) => Number(b.platform === currentPlatform) - Number(a.platform === currentPlatform))
);

watchEffect(() => {
  document.title = t('app_update.download_page.title');
});

onMounted(load);
</script>

<template>
  <main class="min-h-0 flex-1 overflow-y-auto bg-slate-50 px-4 py-8 dark:bg-slate-950 sm:px-6 sm:py-12">
    <div class="mx-auto max-w-4xl space-y-6">
      <header class="space-y-2 text-center">
        <h1 class="text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
          {{ $t('app_update.download_page.title') }}
        </h1>
        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
          {{ $t('app_update.download_page.subtitle') }}
        </p>
      </header>

      <div v-if="isLoading" class="grid grid-cols-1 gap-4 md:grid-cols-2" aria-busy="true">
        <CardSkeleton v-for="n in 2" :key="n" :rows="4" />
      </div>

      <EmptyState v-else-if="hasError" :icon="AlertTriangle" :title="$t('app_update.download_page.load_failed')">
        <button
          type="button"
          class="flex min-h-11 cursor-pointer items-center gap-1.5 rounded-2xl px-5 text-xs font-black transition active:scale-95"
          style="background: var(--color-primary-light); color: var(--color-primary)"
          @click="load"
        >
          <RefreshCw class="h-4 w-4" />
          <span>{{ $t('app_update.retry_update') }}</span>
        </button>
      </EmptyState>

      <EmptyState
        v-else-if="!sortedReleases.length"
        :icon="PackageOpen"
        :title="$t('app_update.download_page.empty_title')"
        :description="$t('app_update.download_page.empty_desc')"
      />

      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <AppDownloadCard
          v-for="release in sortedReleases"
          :key="release.platform"
          :release="release"
          :is-current-platform="release.platform === currentPlatform"
        />
      </div>
    </div>
  </main>
</template>
