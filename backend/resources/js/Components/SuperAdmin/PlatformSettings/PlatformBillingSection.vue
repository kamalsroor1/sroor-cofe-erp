<script setup>
import { ref } from 'vue';
import { Hourglass, Landmark, Percent } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import BaseInput from '../../Form/BaseInput.vue';
import PaymentMethodCard from './PaymentMethodCard.vue';
import PaymentMethodPreview from './PaymentMethodPreview.vue';

defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
});

defineEmits(['update:field']);

const METHODS = ['vodafone_cash', 'instapay', 'bank_transfer'];
// The card being edited: its preview sits beside the cards (desktop) or under the card (phone/tablet).
const activeMethod = ref(METHODS[0]);
</script>

<template>
  <div class="space-y-6">
    <DashboardSectionCard
      :title="$t('billing.console.settings.methods_title')"
      :subtitle="$t('billing.console.settings.methods_subtitle')"
      :icon="Landmark"
      icon-bg="bg-emerald-100 dark:bg-emerald-500/15"
      icon-color="text-emerald-600 dark:text-emerald-400"
      wrapper-class="shadow-sm dark:shadow-xl"
    >
      <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-4">
          <PaymentMethodCard
            v-for="method in METHODS"
            :key="method"
            :method="method"
            :form="form"
            :errors="errors"
            :readonly="readonly"
            :active="activeMethod === method"
            @focus-method="activeMethod = $event"
            @update:field="(key, value) => $emit('update:field', key, value)"
          />
        </div>
        <PaymentMethodPreview class="hidden lg:block lg:sticky lg:top-20" :method="activeMethod" :form="form" />
      </div>
    </DashboardSectionCard>

    <DashboardSectionCard
      :title="$t('billing.console.settings.general_title')"
      :icon="Percent"
      icon-bg="bg-indigo-100 dark:bg-indigo-500/15"
      icon-color="text-indigo-600 dark:text-indigo-400"
      wrapper-class="shadow-sm dark:shadow-xl"
    >
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
        <BaseInput
          :model-value="form.signup_trial_days"
          type="number"
          dir="ltr"
          inputmode="numeric"
          min="1"
          max="90"
          :leading-icon="Hourglass"
          :label="$t('billing.console.settings.signup_trial_days')"
          :placeholder="$t('billing.console.settings.signup_trial_days_placeholder')"
          :hint="$t('billing.console.settings.signup_trial_days_hint')"
          :error="errors.signup_trial_days"
          :readonly="readonly"
          data-testid="platform-signup-trial-days"
          @update:model-value="$emit('update:field', 'signup_trial_days', $event)"
        />
      </div>
    </DashboardSectionCard>
  </div>
</template>
