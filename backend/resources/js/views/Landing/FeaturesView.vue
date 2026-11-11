<script setup>
import { computed, onMounted } from 'vue';
import { ArrowLeft, Layers } from 'lucide-vue-next';
import LandingFinalCta from '../../Components/Landing/LandingFinalCta.vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import MarketingButton from '../../Components/Landing/MarketingButton.vue';
import MarketingPageHeader from '../../Components/Landing/MarketingPageHeader.vue';
import FeaturesChipBar from '../../Components/Landing/Features/FeaturesChipBar.vue';
import FeaturesCoffeeBand from '../../Components/Landing/Features/FeaturesCoffeeBand.vue';
import FeaturesGroups from '../../Components/Landing/Features/FeaturesGroups.vue';
import FeaturesPlanNote from '../../Components/Landing/Features/FeaturesPlanNote.vue';
import { FEATURE_GROUPS, featureGroupId } from '../../Components/Landing/Features/featureGroups';
import { usePublicPlans } from '../../Composables/usePublicPlans';
import { useScrollSpy } from '../../Composables/useScrollSpy';

const { brand, load } = usePublicPlans();
const { activeId, scrollTo } = useScrollSpy(FEATURE_GROUPS.map((group) => featureGroupId(group.key)));

const activeKey = computed(() => FEATURE_GROUPS.find((group) => featureGroupId(group.key) === activeId.value)?.key);
const bandGroup = FEATURE_GROUPS.find((group) => group.layout === 'band');

onMounted(load);
</script>

<template>
  <LandingShell :brand="brand">
    <MarketingPageHeader
      :icon="Layers"
      :kicker="$t('landing.pages.features.kicker')"
      :title="$t('landing.pages.features.title')"
      :subtitle="$t('landing.pages.features.subtitle')"
    >
      <div class="hidden gap-3 pt-2 sm:flex">
        <MarketingButton to="/signup" :icon="ArrowLeft">{{ $t('landing.request_trial') }}</MarketingButton>
        <MarketingButton :to="{ name: 'landing.pricing' }" variant="ghost-dark">
          {{ $t('marketing.features.see_pricing') }}
        </MarketingButton>
      </div>
    </MarketingPageHeader>
    <FeaturesChipBar
      :groups="FEATURE_GROUPS"
      :active-key="activeKey"
      @select="(key) => scrollTo(featureGroupId(key))"
    />
    <FeaturesGroups :groups="FEATURE_GROUPS" />
    <FeaturesCoffeeBand :group="bandGroup" />
    <FeaturesPlanNote />
    <LandingFinalCta />
  </LandingShell>
</template>
