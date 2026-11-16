<template>
  <div class="space-y-3">
    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $t('super.plan_addons_hint') }}</p>

    <EmptyState v-if="!catalog.length" :title="$t('super.plan_addons_empty')" />

    <div
      v-for="(row, index) in form.addons"
      :key="row.addon_id"
      class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 space-y-3"
    >
      <BaseSwitch
        :model-value="row.is_available"
        :label="addonOf(row).name"
        :description="addonOf(row).key"
        @update:model-value="$emit('update:addon', index, 'is_available', $event)"
      />

      <div v-if="addonOf(row).includable && row.is_available" class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
        <BaseInput
          :model-value="row.included_quantity"
          :label="$t('super.plan_addon_included_qty_label')"
          :error="errors[`addons.${index}.included_quantity`]"
          type="number"
          inputmode="numeric"
          min="0"
          input-class="font-mono text-start"
          @update:model-value="$emit('update:addon', index, 'included_quantity', $event)"
        />
        <BaseSwitch
          :model-value="row.included_billing_cycle === 'yearly'"
          :label="$t('super.plan_addon_yearly_only_label')"
          :disabled="!(row.included_quantity > 0)"
          :error="errors[`addons.${index}.included_billing_cycle`]"
          @update:model-value="$emit('update:addon', index, 'included_billing_cycle', $event ? 'yearly' : null)"
        />
      </div>
      <p v-for="key in rowErrors(index)" :key="key" class="text-xs font-bold text-rose-500 dark:text-rose-400">
        {{ [].concat(errors[key])[0] }}
      </p>
    </div>
  </div>
</template>

<script setup>
import BaseInput from '../../Form/BaseInput.vue';
import BaseSwitch from '../../Form/BaseSwitch.vue';
import EmptyState from '../../Common/EmptyState.vue';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  catalog: { type: Array, default: () => [] },
});

defineEmits(['update:addon']);

const addonOf = (row) => props.catalog.find((a) => a.id === row.addon_id) || {};

// Errors on the row itself (e.g. addons.2.addon_id / is_available); quantity and cycle show inline.
const rowErrors = (index) =>
  Object.keys(props.errors).filter(
    (k) =>
      k.startsWith(`addons.${index}.`) && !k.endsWith('.included_quantity') && !k.endsWith('.included_billing_cycle')
  );
</script>
