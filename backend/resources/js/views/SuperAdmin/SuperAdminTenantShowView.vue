<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Loading State Skeleton -->
    <div v-if="isLoading" class="p-20 text-center bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl">
      <div class="w-12 h-12 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
      <p class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ $t('super.loading_tenant_details') }}</p>
    </div>

    <template v-else-if="tenant">
      <!-- Executive Header -->
      <TenantShowHeader
        :tenant="tenant"
        :is-impersonating="isImpersonating"
        :is-migrating="isMigrating"
        @impersonate="impersonateTenant"
        @open-status="showStatusModal = true"
        @run-migrations="runMigrations"
        @delete-tenant="deleteTenant"
      />

      <!-- Live Operational Stats Grid -->
      <TenantStatsGrid :stats="stats" />

      <!-- Main Columns Grid: Units Customization & Features Overrides -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- ⚖️ Left Column: Allowed Units for This Tenant (6 Cols) -->
        <div class="lg:col-span-6">
          <TenantUnitsCard
            :tenant-name="tenant.name"
            :tenant-allowed-units="tenantAllowedUnits"
            :global-units-list="globalUnitsList"
            v-model:custom-unit="customTenantUnit"
            :is-saving-units="isSavingUnits"
            @save-units="saveTenantUnits"
            @remove-unit="removeTenantUnit"
            @add-unit="addTenantUnit"
            @add-custom-unit="addCustomUnitDirect"
          />
        </div>

        <!-- 🚀 Right Column: Feature Overrides & Configuration (6 Cols) -->
        <div class="lg:col-span-6">
          <TenantFeaturesMatrixCard
            :features="allFeatures"
            :enabled-features="tenant.enabled_features || []"
            @toggle-feature="toggleFeature"
          />
        </div>
      </div>

      <!-- Status & Plan Modal -->
      <TenantStatusModal
        :show="showStatusModal"
        :form="statusForm"
        :is-updating-status="isUpdatingStatus"
        @update:field="updateStatusField"
        @submit="updateStatusAndPlan"
        @close="showStatusModal = false"
      />
    </template>
  </div>
</template>

<script setup>
import TenantShowHeader from '../../Components/SuperAdmin/TenantShowHeader.vue';
import TenantStatsGrid from '../../Components/SuperAdmin/TenantStatsGrid.vue';
import TenantUnitsCard from '../../Components/SuperAdmin/TenantUnitsCard.vue';
import TenantFeaturesMatrixCard from '../../Components/SuperAdmin/TenantFeaturesMatrixCard.vue';
import TenantStatusModal from '../../Components/SuperAdmin/TenantStatusModal.vue';
import { useSuperAdminTenantShow } from '../../Composables/useSuperAdminTenantShow';

const {
  tenant,
  stats,
  allFeatures,
  globalUnitsList,
  tenantAllowedUnits,
  customTenantUnit,
  isLoading,
  isSavingUnits,
  isImpersonating,
  isMigrating,
  showStatusModal,
  isUpdatingStatus,
  statusForm,
  toggleFeature,
  addTenantUnit,
  addCustomUnitDirect,
  removeTenantUnit,
  saveTenantUnits,
  runMigrations,
  impersonateTenant,
  updateStatusAndPlan,
  updateStatusField,
  deleteTenant,
} = useSuperAdminTenantShow();
</script>
