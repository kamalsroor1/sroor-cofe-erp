<template>
  <div class="bg-white dark:bg-slate-900/90 p-5 sm:p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6 font-tajawal">
    <div class="flex items-center gap-4">
      <router-link
        to="/super-admin/tenants"
        class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center transition cursor-pointer shrink-0 active:scale-95 shadow-xs"
        :title="$t('common.back')"
      >
        <span>→</span>
      </router-link>

      <div class="w-12 sm:w-14 h-12 sm:h-14 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white font-black text-2xl flex items-center justify-center shadow-lg shadow-purple-500/20 shrink-0">
        🏪
      </div>

      <div>
        <div class="flex items-center gap-3 flex-wrap">
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">{{ tenant?.name }}</h1>
          <span
            class="px-3 py-0.5 rounded-full text-xs font-black border"
            :class="getStatusBadgeClass(tenant?.status)"
          >
            {{ getStatusLabel(tenant?.status) }}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-1 font-mono">
          <span>{{ $t('super.tenant_id_label') }} <strong class="text-purple-600 dark:text-purple-400">{{ tenant?.id }}</strong></span>
          <span>•</span>
          <a :href="`http://${tenant?.domain}`" target="_blank" class="text-cyan-600 dark:text-cyan-400 hover:underline flex items-center gap-1 font-bold">
            <span>{{ tenant?.domain }}</span>
            <ExternalLink class="w-3 h-3" />
          </a>
          <span>•</span>
          <span>{{ $t('super.plan_label') }} <strong class="text-theme-primary font-tajawal">{{ tenant?.plan?.name || $t('super.custom_plan') }}</strong></span>
        </div>
      </div>
    </div>

    <!-- Action Buttons Toolbar -->
    <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
      <BaseButton
        type="button"
        variant="primary"
        size="md"
        :loading="isImpersonating"
        @click="$emit('impersonate')"
        class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black shadow-lg shadow-emerald-500/20"
      >
        <span>🚀</span>
        <span>{{ isImpersonating ? $t('super.impersonating_status') : $t('super.impersonate_btn') }}</span>
      </BaseButton>

      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        @click="$emit('open-status')"
      >
        <span>⚙️</span>
        <span>{{ $t('super.edit_status_and_sub_btn') }}</span>
      </BaseButton>

      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        :loading="isMigrating"
        @click="$emit('run-migrations')"
        class="text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800"
      >
        <span>🗄️</span>
        <span>{{ isMigrating ? $t('super.migrating_status') : $t('super.run_migrations_btn') }}</span>
      </BaseButton>

      <button
        type="button"
        @click="$emit('delete-tenant')"
        class="p-2.5 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-500 rounded-xl transition cursor-pointer active:scale-95"
        :title="$t('super.delete_tenant_title')"
      >
        <Trash2 class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  tenant: { type: Object, default: null },
  isImpersonating: { type: Boolean, default: false },
  isMigrating: { type: Boolean, default: false },
});

defineEmits(['impersonate', 'open-status', 'run-migrations', 'delete-tenant']);

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
        case 'trial': return 'bg-theme-light text-theme-primary border-theme-border';
        case 'suspended': return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30';
        default: return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/30';
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
