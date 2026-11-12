<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { AlertCircle, Send, Wallet } from 'lucide-vue-next';
import AppModal from '@/Components/Common/AppModal.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import BaseInput from '@/Components/Form/BaseInput.vue';
import BaseSelect from '@/Components/Form/BaseSelect.vue';
import BillingPaymentInstructions from './BillingPaymentInstructions.vue';
import BillingReceiptPicker from './BillingReceiptPicker.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';
import { PAYABLE_METHODS, compareDecimalStrings, isValidDecimal, receiptErrorKey, todayIso } from '@/helpers/billing';

const props = defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  instructions: { type: Object, default: () => ({}) },
  supportPhone: { type: String, default: '' },
  submitter: { type: Function, required: true },
});

const emit = defineEmits(['close', 'paid']);

const { t } = useTrans();
const { formatMoney } = useFormatters();

const form = reactive({ amount: '', method: 'instapay', payer_reference: '', paid_at: todayIso(), receipt: null });
const errors = reactive({});
const serverError = ref('');
const submitting = ref(false);

const methodOptions = computed(() =>
  PAYABLE_METHODS.map((m) => ({ value: m, label: t(`billing.billing_payment_method.${m}`) }))
);
const remaining = computed(() => props.invoice?.remaining ?? '0');
// SELL-07: warn before sending an amount below what is left to pay.
const isPartial = computed(() => {
  const amount = String(form.amount ?? '').trim();
  return (
    isValidDecimal(amount) &&
    compareDecimalStrings(amount, '0') > 0 &&
    compareDecimalStrings(amount, remaining.value) < 0
  );
});

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    Object.assign(form, {
      amount: props.invoice?.remaining ?? '',
      method: 'instapay',
      payer_reference: '',
      paid_at: todayIso(),
      receipt: null,
    });
    Object.keys(errors).forEach((key) => delete errors[key]);
    serverError.value = '';
  }
);

const validate = () => {
  const next = {};
  const amount = String(form.amount ?? '').trim();
  if (!isValidDecimal(amount) || compareDecimalStrings(amount, '0') <= 0)
    next.amount = t('subscription.billing.pay.errors.amount_invalid');
  else if (compareDecimalStrings(amount, remaining.value) > 0)
    next.amount = t('subscription.billing.pay.errors.amount_out_of_range', { remaining: formatMoney(remaining.value) });
  if (!PAYABLE_METHODS.includes(form.method)) next.method = t('subscription.billing.pay.errors.method_required');
  const reference = form.payer_reference.trim();
  if (reference.length < 3 || reference.length > 100)
    next.payer_reference = t('subscription.billing.pay.errors.reference_length');
  if (!form.paid_at) next.paid_at = t('subscription.billing.pay.errors.paid_at_required');
  else if (form.paid_at > todayIso()) next.paid_at = t('subscription.billing.pay.errors.paid_at_future');
  const receiptKey = receiptErrorKey(form.receipt);
  if (receiptKey) next.receipt = t(receiptKey);
  Object.keys(errors).forEach((key) => delete errors[key]);
  Object.assign(errors, next);
  return Object.keys(next).length === 0;
};

const submit = async () => {
  serverError.value = '';
  if (!validate()) return;
  submitting.value = true;
  const result = await props.submitter(props.invoice.id, { ...form, payer_reference: form.payer_reference.trim() });
  submitting.value = false;
  if (result.ok) {
    emit('paid');
    return;
  }
  serverError.value = result.message || '';
  Object.entries(result.fields || {}).forEach(([key, messages]) => {
    errors[key] = Array.isArray(messages) ? messages[0] : messages;
  });
};
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('subscription.billing.pay.title', { number: invoice?.number || '' })"
    :icon="Wallet"
    max-width="lg"
    :closeable="!submitting"
    @close="emit('close')"
  >
    <form v-if="invoice" class="space-y-4 py-3 font-tajawal" novalidate @submit.prevent="submit">
      <BillingPaymentInstructions :instructions="instructions" :support-phone="supportPhone" />

      <div
        v-if="serverError"
        class="flex items-start gap-2 rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 p-3 text-xs font-bold text-rose-700 dark:text-rose-300"
        role="alert"
      >
        <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
        <span>{{ serverError }}</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          v-model="form.amount"
          :label="$t('subscription.billing.pay.amount')"
          inputmode="decimal"
          dir="ltr"
          :error="errors.amount"
          required
        />
        <p v-if="isPartial" class="sm:col-span-2 text-xs font-bold text-amber-600 dark:text-amber-400">
          {{ $t('subscription.billing.pay.partial_warning', { remaining: formatMoney(remaining) }) }}
        </p>
        <BaseSelect
          v-model="form.method"
          :label="$t('subscription.billing.pay.method')"
          :options="methodOptions"
          :searchable="false"
          :error="errors.method"
          required
        />
        <BaseInput
          v-model="form.payer_reference"
          :label="$t('subscription.billing.pay.payer_reference')"
          maxlength="100"
          :error="errors.payer_reference"
          required
        />
        <BaseInput
          v-model="form.paid_at"
          type="date"
          :max="todayIso()"
          :label="$t('subscription.billing.pay.paid_at')"
          :error="errors.paid_at"
          required
        />
      </div>

      <BillingReceiptPicker v-model="form.receipt" :error="errors.receipt || ''" />

      <BaseButton type="submit" variant="primary" size="lg" full-width :icon="Send" :loading="submitting">
        {{ $t('subscription.billing.pay.submit') }}
      </BaseButton>
    </form>
  </AppModal>
</template>
