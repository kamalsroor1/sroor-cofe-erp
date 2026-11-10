<script setup>
import { Landmark, Smartphone, UserRound, Percent } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';

defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
});

defineEmits(['update:field']);
</script>

<template>
  <DashboardSectionCard
    :title="$t('billing.console.settings.title')"
    :subtitle="$t('billing.console.settings.subtitle')"
    :icon="Landmark"
    icon-bg="bg-emerald-100 dark:bg-emerald-500/15"
    icon-color="text-emerald-600 dark:text-emerald-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <BaseInput
        :model-value="form.billing_instapay_address"
        dir="ltr"
        :leading-icon="Landmark"
        :label="$t('billing.console.settings.instapay_address')"
        :placeholder="$t('billing.console.settings.instapay_address_placeholder')"
        :hint="$t('billing.console.settings.shown_to_shops_hint')"
        :error="errors.billing_instapay_address"
        :readonly="readonly"
        maxlength="100"
        @update:model-value="$emit('update:field', 'billing_instapay_address', $event)"
      />
      <BaseInput
        :model-value="form.billing_vodafone_cash_number"
        type="tel"
        dir="ltr"
        inputmode="tel"
        :leading-icon="Smartphone"
        :label="$t('billing.console.settings.vodafone_cash_number')"
        :placeholder="$t('billing.console.settings.vodafone_cash_number_placeholder')"
        :hint="$t('billing.console.settings.shown_to_shops_hint')"
        :error="errors.billing_vodafone_cash_number"
        :readonly="readonly"
        maxlength="30"
        @update:model-value="$emit('update:field', 'billing_vodafone_cash_number', $event)"
      />
      <BaseInput
        :model-value="form.billing_account_name"
        :leading-icon="UserRound"
        :label="$t('billing.console.settings.account_name')"
        :placeholder="$t('billing.console.settings.account_name_placeholder')"
        :hint="$t('billing.console.settings.shown_to_shops_hint')"
        :error="errors.billing_account_name"
        :readonly="readonly"
        maxlength="100"
        @update:model-value="$emit('update:field', 'billing_account_name', $event)"
      />
      <BaseInput
        :model-value="form.billing_tax_rate"
        dir="ltr"
        inputmode="decimal"
        :leading-icon="Percent"
        :label="$t('billing.console.settings.tax_rate')"
        :placeholder="$t('billing.console.settings.tax_rate_placeholder')"
        :hint="$t('billing.console.settings.tax_rate_hint')"
        :error="errors.billing_tax_rate"
        :readonly="readonly"
        maxlength="7"
        @update:model-value="$emit('update:field', 'billing_tax_rate', $event)"
      />
      <BaseTextarea
        :model-value="form.billing_payment_note"
        wrapper-class="sm:col-span-2"
        rows="3"
        :label="$t('billing.console.settings.payment_note')"
        :placeholder="$t('billing.console.settings.payment_note_placeholder')"
        :hint="$t('billing.console.settings.shown_to_shops_hint')"
        :error="errors.billing_payment_note"
        :readonly="readonly"
        maxlength="500"
        @update:model-value="$emit('update:field', 'billing_payment_note', $event)"
      />
    </div>
  </DashboardSectionCard>
</template>
