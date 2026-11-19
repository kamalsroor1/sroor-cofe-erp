<template>
  <div
    class="min-h-screen bg-slate-200 dark:bg-slate-950 py-6 px-4 font-tajawal print:bg-white print:p-0 print:min-h-0"
    :dir="appConfig.dir"
  >
    <LabelsPrintToolbar
      class="no-print"
      :result="result"
      :page-count="pages.length"
      :can-print="isReady && result.labels.length > 0"
      @print="print"
      @back="goBack"
    />

    <EmptyState
      v-if="!job || result.labels.length === 0"
      class="no-print"
      :icon="Tag"
      :title="$t('inventory.labels.no_job_title')"
      :description="$t('inventory.labels.no_job_text')"
      data-testid="labels-empty"
    />
    <template v-else>
      <div v-if="!isReady" class="no-print mx-auto max-w-md space-y-3" aria-busy="true" data-testid="labels-skeleton">
        <Skeleton height="h-28" />
        <Skeleton height="h-28" />
      </div>
      <ItemLabelsSheet :class="{ invisible: !isReady }" :pages="pages" :settings="settings" :geometry="geometry" />
    </template>
  </div>
</template>

<script setup>
import { Tag } from 'lucide-vue-next';
import EmptyState from '../../Components/Common/EmptyState.vue';
import Skeleton from '../../Components/Common/Skeletons/Skeleton.vue';
import LabelsPrintToolbar from '../../Components/Items/Labels/LabelsPrintToolbar.vue';
import ItemLabelsSheet from '../../Components/Items/Labels/ItemLabelsSheet.vue';
import { useItemLabelsPrint } from '../../Composables/useItemLabelsPrint';
import { useAppConfigStore } from '../../stores/appConfig';

const appConfig = useAppConfigStore();
const { job, settings, result, pages, geometry, isReady, print, goBack } = useItemLabelsPrint();
</script>
