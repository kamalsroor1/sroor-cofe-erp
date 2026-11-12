<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <TenantShowSkeleton v-if="isLoading" />

    <template v-else-if="tenant">
      <TenantShowHeader
        :tenant="tenant"
        :is-impersonating="isImpersonating"
        :is-migrating="isMigrating"
        :can-manage="canManageTenants"
        :can-unarchive="canUnarchive"
        :archive-in-progress="archiveInProgress"
        :is-archiving="isArchiving"
        :is-unarchiving="isUnarchiving"
        :is-resetting-owner="isResettingOwner"
        @impersonate="impersonateTenant"
        @open-status="openStatusModal"
        @run-migrations="runMigrations"
        @archive-tenant="deleteTenant"
        @unarchive-tenant="restoreArchivedTenant"
        @reset-owner-password="requestOwnerReset"
      />

      <TenantArchivePanel v-if="showArchivePanel" :archive="archiveInfo" :status="tenant.status || ''" />

      <TenantStatsGrid :stats="stats" />

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
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

        <div class="lg:col-span-6">
          <TenantFeaturesMatrixCard
            :features="allFeatures"
            :enabled-features="tenant.enabled_features || []"
            @toggle-feature="toggleFeature"
          />
        </div>
      </div>

      <TenantBillingSection :tenant-id="tenant.id" :tenant-name="tenant.name || ''" />

      <TenantOwnerPasswordModal
        :show="!!revealedOwner"
        :tenant-name="revealedOwner?.tenantName || ''"
        :owner="revealedOwner?.owner || {}"
        :matched-by="revealedOwner?.matchedBy || 'email'"
        :password="revealedOwner?.password || ''"
        :can-whatsapp="canSendWhatsApp"
        @whatsapp="sendOwnerWhatsApp"
        @close="closeOwnerReveal"
      />

      <EditTenantStatusModal
        :show="showStatusModal"
        :tenant-name="tenant.name || ''"
        :current-status="tenant.status || ''"
        :form="statusForm"
        :errors="statusErrors"
        :is-submitting="isUpdatingStatus"
        @update:field="updateStatusField"
        @submit="updateStatusAndPlan"
        @close="showStatusModal = false"
      />
    </template>
  </div>
</template>

<script setup>
import TenantShowHeader from '../../Components/SuperAdmin/TenantShowHeader.vue';
import TenantShowSkeleton from '../../Components/SuperAdmin/TenantShowSkeleton.vue';
import TenantArchivePanel from '../../Components/SuperAdmin/TenantArchivePanel.vue';
import TenantStatsGrid from '../../Components/SuperAdmin/TenantStatsGrid.vue';
import TenantUnitsCard from '../../Components/SuperAdmin/TenantUnitsCard.vue';
import TenantFeaturesMatrixCard from '../../Components/SuperAdmin/TenantFeaturesMatrixCard.vue';
import TenantBillingSection from '../../Components/SuperAdmin/Billing/TenantBillingSection.vue';
import TenantOwnerPasswordModal from '../../Components/SuperAdmin/TenantOwnerPasswordModal.vue';
import EditTenantStatusModal from '../../Components/SuperAdmin/EditTenantStatusModal.vue';
import { useSuperAdminTenantShow } from '../../Composables/useSuperAdminTenantShow';
import { useTenantOwnerPasswordReset } from '../../Composables/useTenantOwnerPasswordReset';

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
  statusErrors,
  toggleFeature,
  addTenantUnit,
  addCustomUnitDirect,
  removeTenantUnit,
  saveTenantUnits,
  runMigrations,
  impersonateTenant,
  openStatusModal,
  updateStatusAndPlan,
  updateStatusField,
  deleteTenant,
  restoreArchivedTenant,
  archiveInfo,
  showArchivePanel,
  canUnarchive,
  archiveInProgress,
  canManageTenants,
  isArchiving,
  isUnarchiving,
} = useSuperAdminTenantShow();

const {
  revealed: revealedOwner,
  isResetting: isResettingOwner,
  canSendWhatsApp,
  requestReset: requestOwnerReset,
  sendWhatsApp: sendOwnerWhatsApp,
  closeReveal: closeOwnerReveal,
} = useTenantOwnerPasswordReset(() => tenant.value);
</script>
