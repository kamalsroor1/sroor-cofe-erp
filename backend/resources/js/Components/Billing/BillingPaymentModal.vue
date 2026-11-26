<script setup>
import { computed, ref, toRef, watch } from 'vue';
import { AlertCircle, CreditCard, Send } from 'lucide-vue-next';
import AppModal from '@/Components/Common/AppModal.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import BillingInvoiceStatusCard from './BillingInvoiceStatusCard.vue';
import BillingMethodDetails from './BillingMethodDetails.vue';
import BillingMethodPicker from './BillingMethodPicker.vue';
import BillingPaymentExtras from './BillingPaymentExtras.vue';
import BillingReceiptPicker from './BillingReceiptPicker.vue';
import BillingStatusBadge from './BillingStatusBadge.vue';
import SupportContact from './SupportContact.vue';
import { useBillingPaymentForm } from '@/Composables/useBillingPaymentForm';
import { useFormatters } from '@/Composables/useFormatters';
import { invoiceTone } from '@/helpers/billing';
import { isPartlyPaid, pendingPaymentOf, showsPaySteps } from '@/helpers/billingInvoiceState';

const props = defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  // `payment_methods` of /billing/subscription: only fully configured methods.
  methods: { type: Array, default: () => [] },
  support: { type: Object, default: () => ({}) },
  planName: { type: String, default: '' },
  submitter: { type: Function, required: true },
  withdrawing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'paid', 'withdraw', 'view-invoice']);

const { formatMoney, formatCurrency } = useFormatters();

const {
  form,
  errors,
  serverError,
  uploading,
  percent,
  uploadFailed,
  selectedMethod,
  isPartial,
  canSubmit,
  submit,
  cancelUpload,
} = useBillingPaymentForm(toRef(props, 'invoice'), toRef(props, 'methods'), props.submitter, () => emit('paid'));

// A rejected receipt shows its card first; "upload another" opens the steps (the 48 h window is not reset).
const reupload = ref(false);
watch(
  () => [props.show, props.invoice?.id],
  () => {
    reupload.value = false;
  }
);

const rejected = computed(() => props.invoice?.review_state === 'rejected');
const stepsVisible = computed(
  () => !!props.invoice && showsPaySteps(props.invoice) && (!rejected.value || reupload.value)
);
const noMethods = computed(() => props.methods.length === 0);
const partlyPaid = computed(() => isPartlyPaid(props.invoice));
const submitLabelKey = computed(() =>
  partlyPaid.value ? 'subscription.billing.pay.pay_remaining' : 'subscription.billing.pay.send_receipt'
);

const onWithdraw = () => emit('withdraw', pendingPaymentOf(props.invoice));
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('subscription.billing.pay.invoice_header', { number: invoice?.number || '' })"
    :icon="CreditCard"
    max-width="lg"
    :closeable="!uploading"
    @close="emit('close')"
  >
    <div v-if="invoice" class="space-y-3 py-3 font-tajawal">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="space-y-0.5">
          <p class="text-xs font-bold text-slate-500 dark:text-slate-400">
            {{ $t('subscription.billing.pay.amount_due') }}
          </p>
          <p class="text-2xl font-black text-slate-950 dark:text-white" dir="ltr">
            {{ formatCurrency(invoice.remaining ?? invoice.total) }}
          </p>
        </div>
        <BillingStatusBadge :label="invoice.status.label" :tone="invoiceTone(invoice.status.value)" />
      </div>

      <div
        v-if="partlyPaid"
        class="rounded-2xl border border-amber-500 bg-amber-50 dark:bg-amber-500/10 p-3 text-xs font-bold text-amber-900 dark:text-amber-200"
        role="status"
      >
        {{
          $t('subscription.billing.partial_banner', {
            paid: formatMoney(invoice.paid_amount),
            remaining: formatMoney(invoice.remaining),
          })
        }}
      </div>

      <BillingInvoiceStatusCard
        v-if="!uploading && !uploadFailed && !stepsVisible"
        :invoice="invoice"
        :plan-name="planName"
        :busy="withdrawing"
        @withdraw="onWithdraw"
        @upload-again="reupload = true"
        @view-invoice="emit('view-invoice', invoice)"
      />

      <div
        v-if="uploading"
        class="rounded-2xl border border-slate-400 bg-slate-100 dark:bg-slate-800 p-3 space-y-3 text-slate-700 dark:text-slate-200"
        role="status"
      >
        <p class="text-sm font-black">{{ $t('subscription.billing.pay.uploading', { percent }) }}</p>
        <div
          class="h-2 rounded-full bg-slate-300 dark:bg-slate-700 overflow-hidden"
          role="progressbar"
          :aria-valuenow="percent"
          aria-valuemin="0"
          aria-valuemax="100"
        >
          <div class="h-full bg-theme-primary transition-all" :style="{ width: `${percent}%` }"></div>
        </div>
        <BaseButton variant="outline" @click="cancelUpload">{{ $t('common.cancel') }}</BaseButton>
      </div>

      <div
        v-if="uploadFailed"
        class="rounded-2xl border border-rose-500 bg-rose-50 dark:bg-rose-500/10 p-3 space-y-3 text-rose-900 dark:text-rose-200"
        role="alert"
      >
        <p class="flex items-start gap-2 text-sm font-black">
          <AlertCircle class="w-5 h-5 shrink-0" />
          {{ $t('subscription.billing.pay.upload_failed') }}
        </p>
        <BaseButton variant="outline" @click="submit">{{ $t('subscription.billing.pay.retry_upload') }}</BaseButton>
      </div>

      <template v-if="stepsVisible && !uploading && !uploadFailed">
        <EmptyState
          v-if="noMethods"
          :icon="CreditCard"
          :title="$t('subscription.billing.pay.no_methods_title')"
          :description="$t('subscription.billing.pay.no_methods_body')"
        >
          <SupportContact :support="support" />
        </EmptyState>

        <form v-else class="space-y-4" novalidate @submit.prevent="submit">
          <section class="space-y-2">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">
              {{ $t('subscription.billing.pay.step_method') }}
            </h3>
            <BillingMethodPicker v-model="form.method" :methods="methods" :error="errors.method || ''" />
            <BillingMethodDetails v-if="selectedMethod" :method="selectedMethod" />
          </section>

          <template v-if="selectedMethod">
            <div
              v-if="serverError"
              class="flex items-start gap-2 rounded-2xl border border-rose-500 bg-rose-50 dark:bg-rose-500/10 p-3 text-xs font-bold text-rose-900 dark:text-rose-200"
              role="alert"
            >
              <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
              <span>{{ serverError }}</span>
            </div>

            <section class="space-y-2">
              <h3 class="text-sm font-black text-slate-900 dark:text-white">
                {{ $t('subscription.billing.pay.step_receipt') }}
              </h3>
              <BillingReceiptPicker
                v-model="form.receipt"
                :title="$t('subscription.billing.pay.upload_after_transfer')"
                :error="errors.receipt || ''"
              />
            </section>

            <BillingPaymentExtras
              v-model:amount="form.amount"
              v-model:paid-at="form.paid_at"
              v-model:reference="form.payer_reference"
              :errors="errors"
              :is-partial="isPartial"
              :remaining-label="formatMoney(invoice.remaining ?? 0)"
            />

            <BaseButton
              type="submit"
              variant="primary"
              size="lg"
              full-width
              custom-class="min-h-14"
              :icon="Send"
              :disabled="!canSubmit"
            >
              {{ $t(submitLabelKey) }}
            </BaseButton>
          </template>
        </form>
      </template>
    </div>
  </AppModal>
</template>
