<script setup>
import { toRef } from 'vue';
import { ArrowRight } from 'lucide-vue-next';
import BillingPaymentFacts from './BillingPaymentFacts.vue';
import BillingReceiptViewer from './BillingReceiptViewer.vue';
import BillingDecisionForm from './BillingDecisionForm.vue';
import { useBillingReceipt } from '../../../Composables/useBillingReceipt';

const props = defineProps({
  payment: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  now: { type: Number, default: () => Date.now() },
});

defineEmits(['back', 'decided']);

const { receipt, retry } = useBillingReceipt(toRef(props, 'payment'));
</script>

<template>
  <article
    class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
    :data-testid="`billing-detail-${payment.id}`"
  >
    <button
      v-if="compact"
      type="button"
      class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl px-2 text-xs font-black text-slate-700 dark:text-slate-200"
      @click="$emit('back')"
    >
      <ArrowRight class="h-4 w-4 rtl:rotate-0 ltr:rotate-180" aria-hidden="true" />
      <span>{{ $t('billing.console.verify.back_to_queue') }}</span>
    </button>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
      <div class="order-2 space-y-4 xl:order-none">
        <BillingPaymentFacts :payment="payment" :now="now" />
        <BillingDecisionForm
          v-if="canManage && payment.status?.value === 'pending'"
          :payment="payment"
          @decided="$emit('decided', $event)"
        />
      </div>
      <BillingReceiptViewer
        class="order-1 xl:order-none"
        :receipt="receipt"
        :has-receipt="payment.has_receipt"
        :reset-key="payment.id"
        @retry="retry"
      />
    </div>
  </article>
</template>
