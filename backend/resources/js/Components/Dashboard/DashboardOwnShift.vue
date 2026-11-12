<template>
  <div class="space-y-4 sm:space-y-5" data-testid="dashboard-own-shift">
    <section
      class="rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 px-4 py-3 sm:px-5 shadow-xs"
    >
      <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white" data-testid="own-shift-title">
        {{ $t('dashboard.own_shift.title') }}
      </h2>
      <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 font-bold">
        {{ $t('dashboard.own_shift.subtitle') }}
      </p>
    </section>

    <section
      v-if="sections.emptyShift"
      class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs"
      data-testid="own-shift-empty"
    >
      <EmptyState
        :icon="Clock"
        :title="$t('dashboard.own_shift.no_shift_title')"
        :description="$t('dashboard.own_shift.no_shift_description')"
      >
        <router-link
          v-if="canSell"
          to="/pos"
          data-testid="own-shift-pos-cta"
          class="min-h-[44px] px-5 rounded-2xl btn-primary-theme text-sm font-black flex items-center gap-2 transition active:scale-95 shadow-theme-sm cursor-pointer"
        >
          <Zap class="w-4 h-4" />
          <span>{{ $t('dashboard.own_shift.no_shift_action') }}</span>
        </router-link>
      </EmptyState>
    </section>

    <template v-else>
      <div
        class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center gap-3 text-xs"
        data-testid="own-shift-block"
      >
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
        <div class="min-w-0">
          <div class="font-bold text-slate-700 dark:text-slate-300">
            {{ $t('dashboard.own_shift.shift_label', { number: activeShift.shift_number }) }}
          </div>
          <div
            v-if="activeShift.is_own && activeShift.user_name"
            class="text-[11px] text-slate-500 dark:text-slate-400"
          >
            {{ activeShift.user_name }}
          </div>
          <div v-if="activeShift.opened_at" class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
            {{ $t('dashboard.own_shift.opened_at', { time: activeShift.opened_at }) }}
          </div>
        </div>
      </div>

      <p
        v-if="activeShift.is_own === false"
        class="px-4 py-3 rounded-2xl bg-theme-light border border-theme-border text-xs font-bold text-theme-primary"
        data-testid="own-shift-branch-notice"
        role="note"
      >
        {{ $t('dashboard.own_shift.branch_shift_notice') }}
      </p>

      <DashboardKpiGrid v-if="sections.kpis" :metrics="metrics" />

      <DashboardQuickActions :actions="quickActions" />

      <DashboardRecentInvoices
        :invoices="recentInvoices"
        :title="$t('dashboard.own_shift.recent_invoices')"
        @preview="$emit('preview', $event)"
      />

      <DashboardLowStock v-if="sections.lowStock" :items="lowStockItems" />
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Clock, Zap } from 'lucide-vue-next';
import EmptyState from '../Common/EmptyState.vue';
import DashboardKpiGrid from './DashboardKpiGrid.vue';
import DashboardQuickActions from './DashboardQuickActions.vue';
import DashboardRecentInvoices from './DashboardRecentInvoices.vue';
import DashboardLowStock from './DashboardLowStock.vue';
import { ownShiftSections } from '../../helpers/dashboardScope';

// Cashier layout (ROLE-01): the caller's own open shift only. No analytics, debts, cash or monthly figures.
const props = defineProps({
  metrics: { type: Object, default: () => ({}) },
  activeShift: { type: Object, default: null },
  recentInvoices: { type: Array, default: () => [] },
  lowStockItems: { type: Array, default: () => [] },
  quickActions: { type: Array, default: () => [] },
  canSell: { type: Boolean, default: false },
});
defineEmits(['preview']);

const sections = computed(() => ownShiftSections(props));
</script>
