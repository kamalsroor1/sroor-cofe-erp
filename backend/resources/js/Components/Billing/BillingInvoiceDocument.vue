<script setup>
import { computed } from 'vue';
import { useAppConfigStore } from '@/stores/appConfig';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
  invoice: { type: Object, required: true },
});

const appConfig = useAppConfigStore();
const { formatMoney, formatDate, formatDateTime } = useFormatters();

const isPaid = computed(() => props.invoice.status.value === 'paid');
const logo = computed(() => appConfig.branding?.logo_light || appConfig.branding?.logo || '');
</script>

<template>
  <article
    class="relative mx-auto w-full max-w-[210mm] bg-white text-slate-900 p-6 sm:p-10 shadow-xl rounded-lg print:shadow-none print:rounded-none print:p-0 font-tajawal text-sm"
  >
    <div
      v-if="isPaid"
      class="pointer-events-none absolute inset-0 flex items-center justify-center select-none"
      aria-hidden="true"
    >
      <span
        class="-rotate-12 border-8 border-emerald-500/40 text-emerald-500/40 rounded-2xl px-8 py-2 text-6xl font-black"
      >
        {{ $t('subscription.billing.print.paid_stamp') }}
      </span>
    </div>

    <header class="flex items-start justify-between gap-4 border-b-2 border-slate-900 pb-4">
      <div class="flex items-center gap-3">
        <img v-if="logo" :src="logo" alt="" class="h-14 w-auto object-contain" />
        <div>
          <p class="text-lg font-black">{{ appConfig.platformName }}</p>
          <p class="text-xs font-bold text-slate-500">{{ $t('subscription.billing.print.heading') }}</p>
        </div>
      </div>
      <div class="text-end space-y-0.5">
        <p class="text-lg font-black" dir="ltr">{{ invoice.number }}</p>
        <p class="text-xs font-bold text-slate-600">{{ invoice.type.label }} - {{ invoice.status.label }}</p>
      </div>
    </header>

    <section class="grid grid-cols-2 gap-4 py-4 text-xs">
      <div class="space-y-1">
        <p class="font-black text-slate-500">{{ $t('subscription.billing.print.invoice_to') }}</p>
        <p class="font-black text-sm">{{ appConfig.companyName }}</p>
      </div>
      <dl class="space-y-1">
        <div v-if="invoice.issued_at" class="flex justify-between gap-2">
          <dt class="font-bold text-slate-500">{{ $t('subscription.billing.details.issued_at') }}</dt>
          <dd class="font-black">{{ formatDate(invoice.issued_at) }}</dd>
        </div>
        <div v-if="invoice.due_at" class="flex justify-between gap-2">
          <dt class="font-bold text-slate-500">{{ $t('subscription.billing.invoices.due') }}</dt>
          <dd class="font-black">{{ formatDate(invoice.due_at) }}</dd>
        </div>
        <div v-if="invoice.period_start" class="flex justify-between gap-2">
          <dt class="font-bold text-slate-500">{{ $t('subscription.billing.details.period') }}</dt>
          <dd class="font-black">{{ formatDate(invoice.period_start) }} - {{ formatDate(invoice.period_end) }}</dd>
        </div>
        <div v-if="invoice.paid_at" class="flex justify-between gap-2">
          <dt class="font-bold text-slate-500">{{ $t('subscription.billing.details.paid_at') }}</dt>
          <dd class="font-black">{{ formatDate(invoice.paid_at) }}</dd>
        </div>
      </dl>
    </section>

    <table class="w-full text-xs border-collapse">
      <thead>
        <tr class="bg-slate-100 text-slate-700">
          <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.description') }}</th>
          <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.quantity') }}</th>
          <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.unit_price') }}</th>
          <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.amount') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(line, index) in invoice.lines" :key="index" class="border-b border-slate-200">
          <td class="p-2 font-bold">{{ line.description }}</td>
          <td class="p-2">{{ line.quantity }}</td>
          <td class="p-2">{{ formatMoney(line.unit_price) }}</td>
          <td class="p-2 font-black">{{ formatMoney(line.amount) }}</td>
        </tr>
      </tbody>
    </table>

    <dl class="ms-auto mt-4 max-w-xs space-y-1.5 text-xs font-bold">
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
      <div class="flex justify-between gap-3 border-t-2 border-slate-900 pt-1.5 text-sm font-black">
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

    <section v-if="invoice.payments.length" class="mt-6 space-y-2">
      <h4 class="text-xs font-black">{{ $t('subscription.billing.details.payments') }}</h4>
      <table class="w-full text-xs border-collapse">
        <thead>
          <tr class="bg-slate-100 text-slate-700">
            <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.submitted_at') }}</th>
            <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.method') }}</th>
            <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.reference') }}</th>
            <th class="p-2 text-start font-black">{{ $t('subscription.billing.details.amount') }}</th>
            <th class="p-2 text-start font-black">{{ $t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in invoice.payments" :key="payment.id" class="border-b border-slate-200">
            <td class="p-2">{{ formatDateTime(payment.submitted_at || payment.paid_at) }}</td>
            <td class="p-2">{{ payment.method.label }}</td>
            <td class="p-2" dir="ltr">{{ payment.payer_reference }}</td>
            <td class="p-2 font-black">{{ formatMoney(payment.amount) }}</td>
            <td class="p-2 font-bold">{{ payment.status.label }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <p v-if="invoice.notes" class="mt-4 text-xs font-medium text-slate-600">{{ invoice.notes }}</p>
  </article>
</template>
