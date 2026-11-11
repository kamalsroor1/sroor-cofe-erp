<script setup>
import { computed } from 'vue';
import { ChevronDown, Download } from 'lucide-vue-next';
import MarketingButton from '../Landing/MarketingButton.vue';
import MarketingIconBadge from '../Landing/MarketingIconBadge.vue';
import AppDownloadChecksum from './AppDownloadChecksum.vue';
import { downloadPlatform } from './appDownloadPlatforms';
import { useFormatters } from '../../Composables/useFormatters';
import { bytesToMegabytes } from '../../helpers/appUpdate';

const props = defineProps({
  /** normalizeDownloadRelease() output: platform, versionName, downloadUrl, checksum, sizeBytes, publishedAt. */
  release: { type: Object, required: true },
  /** featured = the visitor's platform (big card, checksum open); compact = the other platforms. */
  featured: { type: Boolean, default: false },
  isCurrentPlatform: { type: Boolean, default: false },
});

const { formatDate } = useFormatters();

const platform = computed(() => downloadPlatform(props.release.platform));
const sizeMb = computed(() => (props.release.sizeBytes > 0 ? bytesToMegabytes(props.release.sizeBytes) : null));
const meta = computed(() =>
  [
    { key: 'version', label: 'marketing.download.version', value: props.release.versionName },
    {
      key: 'size',
      label: 'marketing.download.size',
      value: sizeMb.value ? { key: 'app_update.download_page.size_mb', size: sizeMb.value } : null,
    },
    {
      key: 'published',
      label: 'marketing.download.published',
      value: props.release.publishedAt ? formatDate(props.release.publishedAt) : null,
    },
  ].filter((row) => row.value)
);
</script>

<template>
  <article
    class="flex flex-col rounded-3xl bg-mk-surface"
    :class="
      featured ? 'gap-[18px] border-2 border-mk-accent p-6 mk-shadow-card lg:p-8' : 'gap-4 border border-mk-line p-6'
    "
    :data-testid="`app-download-card-${release.platform}`"
  >
    <header class="flex items-start gap-3.5">
      <MarketingIconBadge :icon="platform.icon" :size="featured ? 56 : 48" />
      <div class="flex min-w-0 flex-1 flex-col items-start gap-1.5">
        <h2 class="font-bold text-mk-ink" :class="featured ? 'text-[22px]' : 'text-[19px]'">
          {{ $t(platform.titleKey) }}
        </h2>
        <span
          v-if="isCurrentPlatform"
          class="inline-flex min-h-[26px] items-center rounded-full bg-mk-soft px-3 text-xs font-bold text-mk-soft-ink"
        >
          {{ $t('app_update.download_page.your_device') }}
        </span>
        <p v-if="!featured" class="text-sm leading-[1.55] text-mk-muted">{{ $t(platform.bodyKey) }}</p>
      </div>
    </header>

    <dl v-if="meta.length" :class="featured ? 'flex flex-col' : 'flex flex-wrap gap-x-4 gap-y-1 text-[13px]'">
      <div
        v-for="row in meta"
        :key="row.key"
        class="flex items-center gap-1.5"
        :class="featured ? 'justify-between border-b border-mk-line py-2.5 text-sm' : ''"
      >
        <dt class="text-mk-muted">{{ $t(row.label) }}</dt>
        <dd class="font-bold text-mk-ink">
          <span v-if="row.key === 'version'" dir="ltr">{{ row.value }}</span>
          <template v-else-if="row.key === 'size'">{{ $t(row.value.key, { size: row.value.size }) }}</template>
          <template v-else>{{ row.value }}</template>
        </dd>
      </div>
    </dl>

    <MarketingButton
      :href="release.downloadUrl"
      rel="noopener"
      :variant="featured ? 'accent' : 'outline'"
      :size="featured ? 'lg' : 'md'"
      :icon="Download"
      :flip-icon="false"
      block
      :data-testid="`app-download-link-${release.platform}`"
    >
      {{ $t(platform.ctaKey) }}
    </MarketingButton>

    <AppDownloadChecksum v-if="featured" :checksum="release.checksum" :verify-command="platform.verifyCommand" />
    <details v-else class="group rounded-xl">
      <summary
        class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-xl text-sm font-bold text-mk-muted focus-visible:outline-2 focus-visible:outline-mk-primary [&::-webkit-details-marker]:hidden"
      >
        {{ $t('marketing.download.show_checksum') }}
        <ChevronDown class="size-4 shrink-0 transition group-open:rotate-180" aria-hidden="true" />
      </summary>
      <AppDownloadChecksum :checksum="release.checksum" :verify-command="platform.verifyCommand" />
    </details>

    <p class="text-[13px] leading-[1.55] text-mk-faint">{{ $t(platform.installHintKey) }}</p>
  </article>
</template>
