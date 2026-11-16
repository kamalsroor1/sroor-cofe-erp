<template>
  <fieldset class="space-y-3">
    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $t('super.plan_limits_hint') }}</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div v-for="field in fields" :key="field.key" class="flex flex-col gap-1">
        <BaseLimitInput
          :model-value="form[field.key]"
          :label="$t(field.label)"
          :min="field.min"
          :error="errorText(field.key)"
          @update:model-value="$emit('update:field', field.key, $event)"
        />
        <p v-if="field.key === 'max_storage_mb' && storageHint" class="text-[11px] text-slate-500 dark:text-slate-400">
          {{ storageHint }}
        </p>
      </div>
    </div>
  </fieldset>
</template>

<script setup>
import { computed } from 'vue';
import BaseLimitInput from '../../Form/BaseLimitInput.vue';
import { EDITOR_LIMIT_FIELDS } from '../../../helpers/planEditor';
import { storageParts } from '../../../helpers/billing';
import { useFormatters } from '../../../Composables/useFormatters';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
});

defineEmits(['update:field']);

const { t } = useTrans();
const { formatNumber } = useFormatters();

// GB hint under the MB input, only for a finite value of at least 1 GB.
const storageHint = computed(() => {
  const mb = props.form.max_storage_mb;
  if (mb === null || mb === undefined || mb === '') return '';
  const { value, unit } = storageParts(mb);
  return unit === 'gb' ? t('super.storage_limit_gb_hint', { gb: formatNumber(value) }) : '';
});
const fields = EDITOR_LIMIT_FIELDS;

// Client errors are { min, max }; server (422) errors are string arrays.
const errorText = (key) => {
  const error = props.errors?.[key];
  if (!error) return null;
  if (Array.isArray(error) || typeof error === 'string') return error;
  return t('super.limit_invalid', error);
};
</script>
