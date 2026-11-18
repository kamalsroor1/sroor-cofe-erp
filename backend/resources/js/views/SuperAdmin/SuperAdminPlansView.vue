<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader :title="$t('super.plans_page_title')" :subtitle="$t('super.plans_page_subtitle')" icon="briefcase">
      <template #actions>
        <div class="flex flex-wrap items-center gap-3">
          <BaseButton
            v-if="canManage"
            type="button"
            variant="primary"
            size="md"
            class="font-black"
            @click="editor.open()"
          >
            <Plus class="w-4 h-4 me-1.5" aria-hidden="true" />
            {{ $t('super.new_plan_btn') }}
          </BaseButton>

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

    <PlansGrid
      :plans="plans"
      :loading="isLoading"
      :has-error="hasError"
      :can-manage="canManage"
      @edit="editor.open($event)"
      @retry="fetchPlans"
    />

    <NearLimitShopsPanel
      v-if="nearLimit.canView.value"
      :rows="nearLimit.rows.value"
      :meta="nearLimit.meta.value"
      :is-loading="nearLimit.isLoading.value"
      :has-error="nearLimit.hasError.value"
      :threshold="NEAR_LIMIT_THRESHOLD"
      @refresh="nearLimit.refresh"
      @retry="nearLimit.fetchRows"
      @page-change="nearLimit.goToPage"
    />

    <PlanEditorModal :editor="editor" :registry="featuresRegistry" :catalog="addonsCatalog" />
  </div>
</template>

<script setup>
import { Crown, Building2, Plus } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import PlansGrid from '../../Components/SuperAdmin/PlansGrid.vue';
import PlanEditorModal from '../../Components/SuperAdmin/PlanEditor/PlanEditorModal.vue';
import NearLimitShopsPanel from '../../Components/SuperAdmin/NearLimitShops/NearLimitShopsPanel.vue';
import { useSuperAdminPlans } from '../../Composables/useSuperAdminPlans';
import { useNearLimitShops, NEAR_LIMIT_THRESHOLD } from '../../Composables/useNearLimitShops';

const { plans, featuresRegistry, addonsCatalog, isLoading, hasError, canManage, fetchPlans, editor } =
  useSuperAdminPlans();
const nearLimit = useNearLimitShops();
</script>
