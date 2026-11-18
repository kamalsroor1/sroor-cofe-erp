<script setup>
import { onMounted } from 'vue';
import { SlidersHorizontal } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import ErrorState from '../../Common/ErrorState.vue';
import EmptyState from '../../Common/EmptyState.vue';
import LimitOverridesTable from './LimitOverridesTable.vue';
import FeatureOverridesList from './FeatureOverridesList.vue';
import OverrideEditModal from './OverrideEditModal.vue';
import TenantOverridesSkeleton from './TenantOverridesSkeleton.vue';
import { useTenantOverrides } from '../../../Composables/useTenantOverrides';

const props = defineProps({
  tenantId: { type: [String, Number], required: true },
});

const o = useTenantOverrides(props.tenantId);

onMounted(o.fetchOverrides);
</script>

<template>
  <DashboardSectionCard
    v-if="o.canView.value"
    :title="$t('plan_overrides.ui.section_title')"
    :subtitle="$t('plan_overrides.ui.section_subtitle')"
    :icon="SlidersHorizontal"
    icon-bg="bg-indigo-100 dark:bg-indigo-500/15"
    icon-color="text-indigo-600 dark:text-indigo-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <TenantOverridesSkeleton v-if="o.isLoading.value" />

    <ErrorState
      v-else-if="o.loadError.value"
      compact
      :title="$t('plan_overrides.ui.load_failed')"
      :message="o.loadError.value"
      @retry="o.fetchOverrides"
    />

    <EmptyState
      v-else-if="!o.limits.value.length && !o.features.value.length"
      :icon="SlidersHorizontal"
      :title="$t('plan_overrides.ui.empty')"
    />

    <div v-else class="space-y-8">
      <section v-if="o.limits.value.length" class="space-y-3">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('plan_overrides.ui.limits_title') }}</h3>
        <LimitOverridesTable
          :limits="o.limits.value"
          :can-manage="o.canManage.value"
          :removing-key="o.removingKey.value"
          @edit="o.openLimit"
          @remove="o.removeLimit"
        />
      </section>

      <section v-if="o.features.value.length" class="space-y-3">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('plan_overrides.ui.features_title') }}</h3>
        <FeatureOverridesList
          :groups="o.featureGroups.value"
          :can-manage="o.canManage.value"
          :removing-key="o.removingKey.value"
          @grant="(feature) => o.openFeature(feature, 'grant')"
          @revoke="(feature) => o.openFeature(feature, 'revoke')"
          @remove="o.removeFeature"
        />
      </section>
    </div>

    <OverrideEditModal
      v-if="o.canManage.value"
      :dialog="o.dialog.value"
      :form="o.form.value"
      :errors="o.errors.value"
      :min-date="o.minExpiry.value"
      :is-submitting="o.isSubmitting.value"
      @close="o.closeDialog"
      @submit="o.submit"
      @update:field="o.setField"
    />
  </DashboardSectionCard>
</template>
