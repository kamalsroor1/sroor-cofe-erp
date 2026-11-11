<script setup>
import { RefreshCw } from 'lucide-vue-next';
import MarketingButton from '../Landing/MarketingButton.vue';
import MarketingIconBadge from '../Landing/MarketingIconBadge.vue';

defineProps({
  icon: { type: [Object, Function], required: true },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  /** Show a retry button (load error). */
  retry: { type: Boolean, default: false },
});

defineEmits(['retry']);
</script>

<template>
  <!-- Empty / error state in the marketing tokens (the shared EmptyState uses the app palette and font). -->
  <div
    class="flex flex-col items-center gap-3.5 rounded-3xl border-2 border-dashed border-mk-line bg-mk-surface px-6 py-10 text-center"
    :role="retry ? 'alert' : undefined"
  >
    <MarketingIconBadge :icon="icon" :size="56" />
    <h2 class="text-xl font-bold text-mk-ink">{{ title }}</h2>
    <p v-if="description" class="max-w-sm text-base leading-[1.6] text-mk-muted">{{ description }}</p>
    <MarketingButton v-if="retry" variant="outline" :icon="RefreshCw" :flip-icon="false" @click="$emit('retry')">
      {{ $t('app_update.retry_update') }}
    </MarketingButton>
  </div>
</template>
