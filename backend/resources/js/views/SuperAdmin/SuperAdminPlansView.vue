<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('super.plans_page_title')"
      :subtitle="$t('super.plans_page_subtitle')"
      icon="💼"
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

          <router-link
            to="/super-admin/tenants"
            class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition active:scale-95"
          >
            <Building2 class="w-4 h-4" />
            <span>{{ $t('super.tenants_management') }}</span>
          </router-link>
        </div>
      </template>
    </PageHeader>

    <!-- Plans Cards Grid -->
    <PlansGrid
      :plans="plans"
      :loading="isLoading"
      @edit="openEditModal"
    />

    <!-- Edit Plan Modal -->
    <EditPlanModal
      :show="showEditModal"
      :form="editForm"
      :is-submitting="isSubmitting"
      @update:field="updateEditField"
      @submit="submitEditPlan"
      @close="showEditModal = false"
    />
  </div>
</template>

<script setup>
import { Crown, Building2 } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import PlansGrid from '../../Components/SuperAdmin/PlansGrid.vue';
import EditPlanModal from '../../Components/SuperAdmin/EditPlanModal.vue';
import { useSuperAdminPlans } from '../../Composables/useSuperAdminPlans';

const {
  plans,
  isLoading,
  isSubmitting,
  showEditModal,
  editForm,
  openEditModal,
  updateEditField,
  submitEditPlan,
} = useSuperAdminPlans();
</script>
