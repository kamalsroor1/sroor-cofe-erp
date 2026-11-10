<script setup>
import { FileText, Printer, Wallet } from 'lucide-vue-next';
import AppModal from '@/Components/Common/AppModal.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import BillingStatusBadge from './BillingStatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { invoiceTone, isPayable, paymentTone } from '@/helpers/billing';

defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
});

const emit = defineEmits(['close', 'pay']);

const { formatMoney, formatDate, formatDateTime } = useFormatters();
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('subscription.billing.details.title', { number: invoice?.number || '' })"
    :icon="FileText"
    max-width="2xl"
    @close="emit('close')"
  >
    <div v-if="invoice" class="space-y-4 py-3 font-tajawal text-xs">
      <div class="flex flex-wrap items-center gap-2">
        <BillingStatusBadge :label="invoice.status.label" :tone="invoiceTone(invoice.status.value)" />
        <span class="font-bold text-slate-500 dark:text-slate-400">{{ invoice.type.label }}</span>
        <span v-if="invoice.period_start" class="font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.details.period') }}: {{ formatDate(invoice.period_start) }} -
          {{ formatDate(invoice.period_end) }}
        </span>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
        <table class="w-full text-start">
          <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400">
            <tr>
              <th class="p-2.5 text-start font-black">{{ $t('subscription.billing.details.description') }}</th>
              <th class="p-2.5 text-start font-black">{{ $t('subscription.billing.details.quantity') }}</th>
              <th class="p-2.5 text-start font-black">{{ $t('subscription.billing.details.amount') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="(line, index) in invoice.lines" :key="index">
              <td class="p-2.5 font-bold text-slate-800 dark:text-slate-200">
                {{ line.description }}
                <span v-if="line.founder" class="ms-1 text-amber-600 dark:text-amber-400"
                  >({{ $t('subscription.billing.details.founder') }})</span
                >
              </td>
              <td class="p-2.5 font-bold">{{ line.quantity }}</td>
              <td class="p-2.5 font-black whitespace-nowrap">{{ formatMoney(line.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <dl class="ms-auto max-w-xs space-y-1.5 font-bold text-slate-700 dark:text-slate-300">
        <div class="flex justify-between gap-3">
          <dt>{{ $t('subscription.billing.details.subtotal') }}</dt>
          <dd>{{ formatMoney(invoice.subtotal) }}</dd>
        </div>
        <div v-if="Number(invoice.discount) > 0" class="flex justify-between gap-3">
          <dt>{{ $t('subscription.billing.details.discount') }}</dt>
          <dd>{{ formatMoney(invoice.discount) }}</dd>
        </div>
        <div v-if="Number(invoice.tax) > 0" class="flex justify-between gap-3">
          <dt>{{ $t('subscription.billing.details.tax', { rate: Number(invoice.tax_rate) }) }}</dt>
          <dd>{{ formatMoney(invoice.tax) }}</dd>
        </div>
        <div class="flex justify-between gap-3 text-sm font-black text-slate-900 dark:text-white">
          <dt>{{ $t('subscription.billing.details.total') }}</dt>
          <dd>{{ formatMoney(invoice.total) }} {{ invoice.currency }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt>{{ $t('subscription.billing.details.paid') }}</dt>
          <dd>{{ formatMoney(invoice.paid_amount) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt>{{ $t('subscription.billing.details.remaining') }}</dt>
          <dd>{{ formatMoney(invoice.remaining) }}</dd>
        </div>
      </dl>

      <section class="space-y-2">
        <h4 class="font-black text-slate-900 dark:text-white">{{ $t('subscription.billing.details.payments') }}</h4>
        <p v-if="!invoice.payments.length" class="font-medium text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.details.no_payments') }}
        </p>
        <ul v-else class="space-y-2">
          <li
            v-for="payment in invoice.payments"
            :key="payment.id"
            class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 space-y-1"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="font-black text-slate-900 dark:text-white"
                >{{ formatMoney(payment.amount) }} {{ payment.currency }}</span
              >
              <BillingStatusBadge :label="payment.status.label" :tone="paymentTone(payment.status.value)" />
            </div>
            <p class="font-bold text-slate-500 dark:text-slate-400">
              {{ payment.method.label }} - {{ $t('subscription.billing.details.reference') }}:
              {{ payment.payer_reference }}
            </p>
            <p v-if="payment.submitted_at" class="font-medium text-slate-500 dark:text-slate-400">
              {{ $t('subscription.billing.details.submitted_at') }}: {{ formatDateTime(payment.submitted_at) }}
            </p>
            <p v-if="payment.rejection_reason" class="font-bold text-rose-600 dark:text-rose-400">
              {{ $t('subscription.billing.details.rejection_reason') }}: {{ payment.rejection_reason }}
            </p>
          </li>
        </ul>
      </section>

      <p v-if="invoice.notes" class="font-medium text-slate-600 dark:text-slate-400">
        {{ $t('subscription.billing.details.notes') }}: {{ invoice.notes }}
      </p>
    </div>

    <template #footer>
      <div v-if="invoice" class="flex flex-wrap justify-end gap-2">
        <BaseButton
          variant="secondary"
          :icon="Printer"
          :to="{ name: 'billing.invoice.print', params: { id: invoice.id } }"
        >
          {{ $t('subscription.billing.details.open_print') }}
        </BaseButton>
        <BaseButton v-if="isPayable(invoice)" variant="primary" :icon="Wallet" @click="emit('pay', invoice)">
          {{ $t('subscription.billing.invoices.pay') }}
        </BaseButton>
      </div>
    </template>
  </AppModal>
</template>
