<template>
  <BaseInput
    v-model="model"
    :label="label"
    :error="error"
    :hint="vatHint"
    type="text"
    inputmode="decimal"
    input-class="font-mono text-start"
    :required="required"
  />
</template>

<script setup>
import { computed } from 'vue';
import BaseInput from '../../Form/BaseInput.vue';
import { useTrans } from '../../../Composables/useTrans';
import { vatInclusiveText } from '../../../helpers/planEditor';
import { vatRateLabel } from '../../../helpers/landingCatalog';

// A money field kept as the typed string (never a Number), with a live VAT-inclusive hint.
const props = defineProps({
  taxRate: { type: String, default: '0.000' },
  label: { type: String, default: '' },
  error: { type: [String, Array], default: null },
  required: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });
const { t } = useTrans();

const vatHint = computed(() => {
  const inclusive = vatInclusiveText(model.value, props.taxRate);
  return inclusive
    ? t('super.plan_vat_inclusive_hint', { amount: inclusive, percent: vatRateLabel(props.taxRate) })
    : '';
});
</script>
