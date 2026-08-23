<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5">
      <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
        <Building2 class="w-4 h-4 text-purple-500" />
        <span>{{ $t('super.recent_tenants') }}</span>
      </h2>
      <router-link to="/super-admin/tenants" class="text-[11px] text-theme-primary hover:underline font-bold">
        {{ $t('super.view_all_tenants_link') }}
      </router-link>
    </div>

    <div v-if="recentTenants.length > 0" class="space-y-2">
      <div
        v-for="t in recentTenants"
        :key="t.id"
        class="p-3 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs"
      >
        <div class="space-y-0.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>{{ t.name }}</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">({{ t.domain }})</span>
          </div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">
            {{ $t('super.subscribed_plan_col') }}: <span class="text-theme-primary font-bold">{{ t.plan_name }}</span>
          </div>
        </div>

        <div class="flex items-center gap-3 self-end sm:self-center">
          <span
            class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
            :class="getStatusBadgeClass(t.status)"
          >
            {{ getStatusLabel(t.status) }}
          </span>
          <span class="text-[10px] text-slate-500 font-mono">{{ t.created_at }}</span>
        </div>
      </div>
    </div>

    <div v-else class="p-8 text-center text-xs text-slate-400 font-bold">
      {{ $t('super.no_tenants_registered') }}
    </div>
  </div>
</template>

<script setup>
import { Building2 } from 'lucide-vue-next';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  recentTenants: { type: Array, default: () => [] },
});

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
        case 'trial': return 'bg-theme-light border-theme-border text-theme-primary';
        case 'suspended': return 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400';
        default: return 'bg-slate-500/10 border-slate-500/30 text-slate-400';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'active': return t('super.status_active_badge');
        case 'trial': return t('super.status_trial_badge');
        case 'suspended': return t('super.status_suspended_badge');
        case 'expired': return t('super.status_expired_badge');
        default: return status;
    }
};
</script>
