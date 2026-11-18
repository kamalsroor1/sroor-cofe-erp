<script setup>
import { Ban, CirclePlus } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';
import OverrideBadge from './OverrideBadge.vue';
import OverrideRowActions from './OverrideRowActions.vue';

/** Features grouped by module: in plan or not, effective on/off, override badge and grant / revoke actions. */
defineProps({
  groups: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  removingKey: { type: String, default: '' },
});

defineEmits(['grant', 'revoke', 'remove']);
</script>

<template>
  <div class="space-y-5" data-testid="feature-overrides">
    <section v-for="group in groups" :key="group.module" class="space-y-2.5">
      <h4 class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">
        {{ group.module || $t('plan_overrides.ui.module_other') }}
      </h4>

      <div
        v-for="feature in group.items"
        :key="feature.key"
        class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start rounded-2xl border p-4 transition"
        :class="
          feature.override
            ? 'border-indigo-500/40 bg-indigo-500/5 dark:bg-indigo-950/20'
            : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40'
        "
        :data-testid="`feature-row-${feature.key}`"
      >
        <div class="md:col-span-3 min-w-0">
          <div class="text-sm font-black text-slate-900 dark:text-white">{{ feature.label }}</div>
          <div class="text-[10px] font-mono text-slate-500 dark:text-slate-400" dir="ltr">{{ feature.key }}</div>
        </div>

        <div class="md:col-span-3 flex flex-wrap items-center gap-2">
          <span
            class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[11px] font-black"
            :class="
              feature.from_plan
                ? 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200'
                : 'border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500'
            "
          >
            {{ $t('plan_overrides.ui.from_plan') }}: {{ feature.from_plan ? $t('common.yes') : $t('common.no') }}
          </span>
          <span
            class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[11px] font-black"
            :class="
              feature.effective
                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                : 'border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
            "
          >
            {{ $t('plan_overrides.ui.effective') }}:
            {{ feature.effective ? $t('plan_overrides.ui.enabled') : $t('plan_overrides.ui.disabled') }}
          </span>
        </div>

        <div class="md:col-span-3 min-w-0">
          <OverrideBadge
            v-if="feature.override"
            :override="feature.override"
            :tone="feature.override.mode === 'grant' ? 'emerald' : 'rose'"
            :label="
              feature.override.mode === 'grant' ? $t('plan_overrides.ui.granted') : $t('plan_overrides.ui.revoked')
            "
          />
          <span v-else class="text-[11px] font-bold text-slate-400 dark:text-slate-500">
            {{ $t('plan_overrides.ui.no_override') }}
          </span>
        </div>

        <div class="md:col-span-3">
          <OverrideRowActions
            :can-manage="canManage"
            :has-override="!!feature.override"
            :is-removing="removingKey === `feature:${feature.key}`"
            @remove="$emit('remove', feature)"
          >
            <template #extra>
              <BaseButton
                v-if="!feature.effective"
                variant="success"
                size="sm"
                class="min-h-[44px]"
                :icon="CirclePlus"
                :label="$t('plan_overrides.ui.grant')"
                @click="$emit('grant', feature)"
              />
              <BaseButton
                v-if="feature.effective"
                variant="outline"
                size="sm"
                class="min-h-[44px]"
                :icon="Ban"
                :label="$t('plan_overrides.ui.revoke')"
                @click="$emit('revoke', feature)"
              />
            </template>
          </OverrideRowActions>
        </div>
      </div>
    </section>
  </div>
</template>
