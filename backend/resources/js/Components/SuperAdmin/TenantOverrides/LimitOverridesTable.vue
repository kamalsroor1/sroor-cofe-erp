<script setup>
import { useFormatters } from '../../../Composables/useFormatters';
import OverrideBadge from './OverrideBadge.vue';
import OverrideRowActions from './OverrideRowActions.vue';

/** Limits of the shop: plan value, override, effective value. null = unlimited. */
defineProps({
  limits: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  removingKey: { type: String, default: '' },
});

defineEmits(['edit', 'remove']);

const { formatNumber } = useFormatters();

const display = (value, unit) =>
  value === null || value === undefined ? null : `${formatNumber(value)} ${unit || ''}`.trim();
</script>

<template>
  <div class="space-y-2.5" data-testid="limit-overrides">
    <div
      class="hidden md:grid grid-cols-12 gap-3 px-4 text-[11px] font-black text-slate-500 dark:text-slate-400"
      aria-hidden="true"
    >
      <span class="col-span-2">{{ $t('plan_overrides.ui.resource') }}</span>
      <span class="col-span-2">{{ $t('plan_overrides.ui.plan_value') }}</span>
      <span class="col-span-4">{{ $t('plan_overrides.ui.override') }}</span>
      <span class="col-span-2">{{ $t('plan_overrides.ui.effective') }}</span>
      <span class="col-span-2 text-end">{{ $t('plan_overrides.ui.actions') }}</span>
    </div>

    <div
      v-for="row in limits"
      :key="row.resource"
      class="grid grid-cols-2 md:grid-cols-12 gap-3 items-start rounded-2xl border p-4 transition"
      :class="
        row.overridden
          ? 'border-indigo-500/40 bg-indigo-500/5 dark:bg-indigo-950/20'
          : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40'
      "
      :data-testid="`limit-row-${row.resource}`"
    >
      <div class="col-span-2 md:col-span-2 min-w-0">
        <div class="text-sm font-black text-slate-900 dark:text-white">{{ row.label }}</div>
        <span v-if="row.overridden" class="text-[11px] font-black text-indigo-600 dark:text-indigo-300">
          {{ $t('plan_overrides.ui.overridden') }}
        </span>
      </div>

      <div class="md:col-span-2 text-sm font-bold text-slate-700 dark:text-slate-200">
        <span class="md:hidden block text-[11px] text-slate-500 dark:text-slate-400">
          {{ $t('plan_overrides.ui.plan_value') }}
        </span>
        {{ display(row.plan_value, row.unit) ?? $t('plan_overrides.ui.unlimited') }}
      </div>

      <div class="md:col-span-2 text-sm font-black text-slate-900 dark:text-white md:order-4">
        <span class="md:hidden block text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ $t('plan_overrides.ui.effective') }}
        </span>
        {{ display(row.effective, row.unit) ?? $t('plan_overrides.ui.unlimited') }}
      </div>

      <div class="col-span-2 md:col-span-4 md:order-3 min-w-0">
        <OverrideBadge
          v-if="row.override"
          :override="row.override"
          :label="display(row.override.value, row.unit) ?? $t('plan_overrides.ui.unlimited')"
        />
        <span v-else class="text-[11px] font-bold text-slate-400 dark:text-slate-500">
          {{ $t('plan_overrides.ui.no_override') }}
        </span>
      </div>

      <div class="col-span-2 md:col-span-2 md:order-5">
        <OverrideRowActions
          :can-manage="canManage"
          show-edit
          :has-override="!!row.override"
          :is-removing="removingKey === `limit:${row.resource}`"
          @edit="$emit('edit', row)"
          @remove="$emit('remove', row)"
        />
      </div>
    </div>
  </div>
</template>
