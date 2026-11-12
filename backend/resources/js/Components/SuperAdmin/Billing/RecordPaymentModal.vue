<script setup>
import { computed } from 'vue';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseSelect from '../../Form/BaseSelect.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import { useTrans } from '../../../Composables/useTrans';
import { RECORD_METHODS } from '../../../Composables/useRecordPayment';

const props = defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const { t } = useTrans();

const methodOptions = computed(() =>
  RECORD_METHODS.map((value) => ({ value, label: t(`billing.billing_payment_method.${value}`) }))
);
const subtitle = computed(() =>
  props.invoice
    ? t('billing.console.record.invoice', { number: props.invoice.number, remaining: props.invoice.remaining })
    : ''
);
const firstError = (key) => props.errors[key]?.[0];
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('billing.console.record.title')"
    :subtitle="subtitle"
    max-width="lg"
    @close="$emit('close')"
  >
    <form class="space-y-4 font-tajawal" novalidate data-testid="record-payment-form" @submit.prevent="$emit('submit')">
      <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
        {{ $t('billing.console.record.hint') }}
      </p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <BaseInput
          :model-value="form.amount"
          dir="ltr"
          inputmode="decimal"
          :label="$t('billing.console.record.amount')"
          :error="firstError('amount')"
          required
          @update:model-value="$emit('update:field', 'amount', $event)"
        />
        <BaseSelect
          :model-value="form.method"
          :options="methodOptions"
          :searchable="false"
          :label="$t('billing.console.record.method')"
          :error="firstError('method')"
          required
          @update:model-value="$emit('update:field', 'method', $event)"
        />
        <BaseInput
          :model-value="form.paid_at"
          type="date"
          dir="ltr"
          :label="$t('billing.console.record.paid_at')"
          :error="firstError('paid_at')"
          required
          @update:model-value="$emit('update:field', 'paid_at', $event)"
        />
        <BaseInput
          :model-value="form.reference"
          dir="ltr"
          maxlength="100"
          :label="$t('billing.console.record.reference')"
          :error="firstError('reference')"
          @update:model-value="$emit('update:field', 'reference', $event)"
        />
      </div>

      <BaseTextarea
        :model-value="form.note"
        :label="$t('billing.console.record.note')"
        :error="firstError('note')"
        maxlength="500"
        rows="2"
        @update:model-value="$emit('update:field', 'note', $event)"
      />

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton
          type="button"
          variant="ghost"
          :label="$t('common.cancel')"
          :disabled="isSubmitting"
          @click="$emit('close')"
        />
        <BaseButton
          type="submit"
          variant="primary"
          :loading="isSubmitting"
          :label="$t('billing.console.record.submit')"
        />
      </div>
    </form>
  </AppModal>
</template>
