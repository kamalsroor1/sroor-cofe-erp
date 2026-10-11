<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader :title="$t('super.super_admin_title')" :subtitle="$t('super.super_admin_subtitle')" :icon="Crown">
      <template #actions>
        <div class="flex items-center gap-3">
          <router-link
            to="/super-admin/tenants"
            class="px-4 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/20 flex items-center gap-2 transition cursor-pointer active:scale-95"
          >
            <Building2 class="w-4 h-4" />
            <span>{{ $t('super.tenants_management') }}</span>
          </router-link>

          <router-link
            to="/super-admin/plans"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 font-bold text-xs rounded-xl shadow-xs flex items-center gap-2 transition active:scale-95"
          >
            <Layers class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('super.plans_management') }}</span>
          </router-link>
        </div>
      </template>
    </PageHeader>

    <!-- 5 Platform Metric Cards -->
    <SuperAdminMetricsGrid :metrics="metrics" :loading="isLoading" />

    <!-- Plans Distribution & Recent Tenants (2 Cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <SuperAdminPlansDistribution :plan-stats="planStats" />

      <div class="lg:col-span-2">
        <SuperAdminRecentTenants :recent-tenants="recentTenants" />
      </div>
    </div>

    <PlatformSettingsLinkCard v-if="canViewSettings" />

    <!-- Central Server & System Information Section -->
    <SuperAdminServerSpecsCard :system-info="systemInfo" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Building2, Layers, Crown } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import SuperAdminMetricsGrid from '../../Components/SuperAdmin/SuperAdminMetricsGrid.vue';
import SuperAdminPlansDistribution from '../../Components/SuperAdmin/SuperAdminPlansDistribution.vue';
import SuperAdminRecentTenants from '../../Components/SuperAdmin/SuperAdminRecentTenants.vue';
import PlatformSettingsLinkCard from '../../Components/SuperAdmin/PlatformSettings/PlatformSettingsLinkCard.vue';
import SuperAdminServerSpecsCard from '../../Components/SuperAdmin/SuperAdminServerSpecsCard.vue';
import { useSuperAdminDashboard } from '../../Composables/useSuperAdminDashboard';
import { useCentralAuthStore } from '../../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../../helpers/centralPermissions';

const { metrics, planStats, recentTenants, systemInfo, isLoading } = useSuperAdminDashboard();

const centralAuth = useCentralAuthStore();
const canViewSettings = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.settingsView));
</script>
