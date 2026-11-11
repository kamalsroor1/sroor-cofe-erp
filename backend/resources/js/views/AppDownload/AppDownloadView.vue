<script setup>
import { onMounted, watchEffect } from 'vue';
import { Download } from 'lucide-vue-next';
import AppDownloadReleases from '../../Components/AppDownload/AppDownloadReleases.vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import MarketingPageHeader from '../../Components/Landing/MarketingPageHeader.vue';
import { useAppDownloadPage } from '../../Composables/useAppDownloadPage';
import { usePublicPlans } from '../../Composables/usePublicPlans';
import { useTrans } from '../../Composables/useTrans';

// APP-3: public manual download page. The native apps' updater opens it when it cannot install safely, so the
// release list, its SHA-256 and the download links must keep working even if the marketing catalog fails.
const { t } = useTrans();
const { featured, others, featuredIsCurrent, isLoading, hasError, load } = useAppDownloadPage();
const { brand, load: loadBrand } = usePublicPlans();

watchEffect(() => {
  document.title = t('app_update.download_page.title');
});

onMounted(() => {
  load();
  loadBrand();
});
</script>

<template>
  <LandingShell :brand="brand">
    <MarketingPageHeader
      :icon="Download"
      :kicker="$t('landing.nav.download')"
      :title="$t('marketing.download.title', { name: brand.name || $t('brand.name') })"
      :subtitle="$t('marketing.download.subtitle')"
    />
    <AppDownloadReleases
      :featured="featured"
      :featured-is-current="featuredIsCurrent"
      :others="others"
      :is-loading="isLoading"
      :has-error="hasError"
      @retry="load"
    />
  </LandingShell>
</template>
