<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('super.tenants_page_title')"
      :subtitle="$t('super.tenants_page_subtitle')"
      icon="🏢"
    >
      <template #actions>
        <div class="flex items-center gap-3">
          <router-link
            to="/super-admin"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition active:scale-95"
          >
            <Crown class="w-4 h-4 text-purple-400" />
            <span>{{ $t('super.dashboard') }}</span>
          </router-link>

          <button
            type="button"
            @click="openCreateModal"
            class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition cursor-pointer active:scale-95"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('super.new_tenant_btn') }}</span>
          </button>
        </div>
      </template>
    </PageHeader>

    <!-- Filters & Search Bar -->
    <TenantsFilterBar
      :filters="filters"
      :status-options="statusOptions"
      :plan-options="planOptions"
      @update:search="updateSearch"
      @update:status="updateStatusFilter"
      @update:plan="updatePlanFilter"
    />

    <!-- Tenants Ledger Table & Mobile Cards -->
    <TenantsTable
      :tenants="tenants"
      :loading="isLoading"
      @open-status="openStatusModal"
      @open-create="openCreateModal"
      @delete-tenant="confirmDeleteTenant"
    />

    <!-- Create Tenant Modal -->
    <CreateTenantModal
      :show="showCreateModal"
      :form="createForm"
      :plans-list="plansList"
      :is-submitting="isSubmitting"
      @update:field="updateCreateField"
      @submit="submitCreateTenant"
      @close="showCreateModal = false"
    />

    <!-- Edit Tenant Status Modal -->
    <EditTenantStatusModal
      :show="showStatusModal"
      :selected-tenant="selectedTenant"
      :form="statusForm"
      :is-submitting="isSubmitting"
      @update:field="updateStatusField"
      @submit="submitStatusChange"
      @close="showStatusModal = false"
    />
  </div>
</template>

<script setup>
import { Crown, Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import TenantsFilterBar from '../../Components/SuperAdmin/TenantsFilterBar.vue';
import TenantsTable from '../../Components/SuperAdmin/TenantsTable.vue';
import CreateTenantModal from '../../Components/SuperAdmin/CreateTenantModal.vue';
import EditTenantStatusModal from '../../Components/SuperAdmin/EditTenantStatusModal.vue';
import { useSuperAdminTenants } from '../../Composables/useSuperAdminTenants';

const {
  tenants,
  plansList,
  isLoading,
  isSubmitting,
  filters,
  statusOptions,
  planOptions,
  showCreateModal,
  showStatusModal,
  selectedTenant,
  createForm,
  statusForm,
  updateSearch,
  updateStatusFilter,
  updatePlanFilter,
  openCreateModal,
  updateCreateField,
  submitCreateTenant,
  openStatusModal,
  updateStatusField,
  submitStatusChange,
  confirmDeleteTenant,
} = useSuperAdminTenants();
</script>
