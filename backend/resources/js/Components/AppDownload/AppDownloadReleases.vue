<script setup>
import { AlertTriangle, PackageOpen } from 'lucide-vue-next';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import AppDownloadBrowserCard from './AppDownloadBrowserCard.vue';
import AppDownloadCard from './AppDownloadCard.vue';
import AppDownloadState from './AppDownloadState.vue';

defineProps({
  /** The visitor's platform release (or the first one), null when none is published. */
  featured: { type: Object, default: null },
  featuredIsCurrent: { type: Boolean, default: false },
  others: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
});

defineEmits(['retry']);
</script>

<template>
  <section class="bg-mk-bg px-5 pt-6 pb-14 sm:px-6 lg:pt-16 lg:pb-24">
    <div class="mx-auto grid max-w-[1072px] items-start gap-4 lg:grid-cols-2 lg:gap-8">
      <div
        v-if="isLoading"
        class="flex flex-col gap-4 rounded-3xl border border-mk-line bg-mk-surface p-8"
        aria-busy="true"
      >
        <div class="flex items-center gap-3.5">
          <Skeleton width="w-14" height="h-14" rounded="rounded-2xl" />
          <Skeleton width="w-1/2" height="h-6" />
        </div>
        <Skeleton v-for="n in 3" :key="n" height="h-5" />
        <Skeleton height="h-14" rounded="rounded-[14px]" />
        <Skeleton height="h-20" />
      </div>
      <AppDownloadState
        v-else-if="hasError"
        :icon="AlertTriangle"
        :title="$t('app_update.download_page.load_failed')"
        retry
        @retry="$emit('retry')"
      />
      <AppDownloadState
        v-else-if="!featured"
        :icon="PackageOpen"
        :title="$t('app_update.download_page.empty_title')"
        :description="$t('app_update.download_page.empty_desc')"
      />
      <AppDownloadCard v-else :release="featured" featured :is-current-platform="featuredIsCurrent" />

      <div class="flex flex-col gap-4">
        <template v-if="isLoading">
          <div class="flex flex-col gap-4 rounded-3xl border border-mk-line bg-mk-surface p-6" aria-hidden="true">
            <div class="flex items-center gap-3.5">
              <Skeleton width="w-12" height="h-12" rounded="rounded-2xl" />
              <Skeleton width="w-2/3" height="h-5" />
            </div>
            <Skeleton height="h-[46px]" rounded="rounded-[14px]" />
          </div>
        </template>
        <template v-else>
          <AppDownloadCard v-for="release in others" :key="release.platform" :release="release" />
        </template>
        <AppDownloadBrowserCard />
      </div>
    </div>
  </section>
</template>
