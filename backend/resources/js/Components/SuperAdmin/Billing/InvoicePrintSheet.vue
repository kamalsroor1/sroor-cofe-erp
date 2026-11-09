<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../../Composables/useFormatters';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  invoice: { type: Object, required: true },
  brand: { type: Object, default: () => ({ name: '', legalName: '', logo: '' }) },
});

const { t } = useTrans();
const { formatMoney, formatDate, formatNumber } = useFormatters();

const brandName = computed(() => props.brand.name || t('super.platform_title'));
const isPaid = computed(() => props.invoice.status?.value === 'paid');
const isVoid = computed(() => props.invoice.status?.value === 'void');
const hasDiscount = computed(() => Number(props.invoice.discount) > 0);
const period = computed(() =>
  props.invoice.period_start && props.invoice.period_end
    ? `${formatDate(props.invoice.period_start)} - ${formatDate(props.invoice.period_end)}`
    : ''
);
const money = (value) => formatMoney(value, 3);
</script>

<template>
  <article
    class="invoice-sheet relative mx-auto w-full max-w-[210mm] bg-white text-slate-900 p-6 sm:p-10 shadow-xl rounded-2xl print:shadow-none print:rounded-none print:p-0 font-tajawal text-sm"
    data-testid="billing-invoice-sheet"
  >
    <div
      v-if="isPaid || isVoid"
      class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
      aria-hidden="true"
    >
      <span
        class="-rotate-12 border-8 rounded-3xl px-8 py-2 text-6xl sm:text-8xl font-black tracking-widest opacity-15"
        :class="isPaid ? 'border-emerald-600 text-emerald-600' : 'border-rose-600 text-rose-600'"
      >
        {{ isPaid ? $t('billing.console.print.paid_stamp') : $t('billing.console.print.void_stamp') }}
      </span>
    </div>

    <header class="flex items-start justify-between gap-4 border-b-2 border-slate-900 pb-5">
      <div class="flex items-center gap-3 min-w-0">
        <img v-if="brand.logo" :src="brand.logo" :alt="brandName" class="h-14 w-auto max-w-40 object-contain" />
        <div class="min-w-0">
          <h1 class="text-xl font-black">{{ brandName }}</h1>
          <p v-if="brand.legalName" class="text-xs font-bold text-slate-500">{{ brand.legalName }}</p>
        </div>
      </div>
      <div class="text-end shrink-0">
        <h2 class="text-lg font-black">{{ $t('billing.console.print.title') }}</h2>
        <p class="text-xs font-bold text-slate-500">{{ invoice.type?.label }}</p>
      </div>
    </header>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 py-5 text-xs">
      <div>
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.number') }}</dt>
        <dd class="font-black text-base" dir="ltr">{{ invoice.number }}</dd>
      </div>
      <div>
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.tenant') }}</dt>
        <dd class="font-black text-base">{{ invoice.tenant?.name }}</dd>
      </div>
      <div>
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.issued') }}</dt>
        <dd class="font-black">{{ formatDate(invoice.issued_at) }}</dd>
      </div>
      <div v-if="invoice.due_at">
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.due') }}</dt>
        <dd class="font-black">{{ formatDate(invoice.due_at) }}</dd>
      </div>
      <div v-if="period">
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.period') }}</dt>
        <dd class="font-black">{{ period }}</dd>
      </div>
      <div>
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.currency') }}</dt>
        <dd class="font-black" dir="ltr">{{ invoice.currency }}</dd>
      </div>
    </dl>

    <table class="w-full text-xs text-start border-collapse">
      <thead>
        <tr class="bg-slate-100 text-slate-700">
          <th class="p-2 text-start font-black">{{ $t('billing.console.print.description') }}</th>
          <th class="p-2 text-start font-black">{{ $t('billing.console.print.quantity') }}</th>
          <th class="p-2 text-start font-black">{{ $t('billing.console.print.unit_price') }}</th>
          <th class="p-2 text-start font-black">{{ $t('billing.console.print.amount') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(line, index) in invoice.lines" :key="index" class="border-b border-slate-200">
          <td class="p-2 font-bold">{{ line.description }}</td>
          <td class="p-2" dir="ltr">{{ line.quantity }}</td>
          <td class="p-2" dir="ltr">{{ money(line.unit_price) }}</td>
          <td class="p-2 font-black" dir="ltr">{{ money(line.amount) }}</td>
        </tr>
      </tbody>
    </table>

    <dl class="ms-auto mt-4 w-full sm:w-72 space-y-1.5 text-xs">
      <div class="flex justify-between gap-4">
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.subtotal') }}</dt>
        <dd class="font-black" dir="ltr">{{ money(invoice.subtotal) }}</dd>
      </div>
      <div v-if="hasDiscount" class="flex justify-between gap-4">
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.discount') }}</dt>
        <dd class="font-black" dir="ltr">- {{ money(invoice.discount) }}</dd>
      </div>
      <div class="flex justify-between gap-4">
        <dt class="font-bold text-slate-500">
          {{ $t('billing.console.print.tax', { rate: formatNumber(invoice.tax_rate, { maximumFractionDigits: 3 }) }) }}
        </dt>
        <dd class="font-black" dir="ltr">{{ money(invoice.tax) }}</dd>
      </div>
      <div class="flex justify-between gap-4 border-t-2 border-slate-900 pt-2 text-base">
        <dt class="font-black">{{ $t('billing.console.print.total') }}</dt>
        <dd class="font-black" dir="ltr">{{ money(invoice.total) }} {{ invoice.currency }}</dd>
      </div>
      <div class="flex justify-between gap-4">
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.paid') }}</dt>
        <dd class="font-black text-emerald-700" dir="ltr">{{ money(invoice.paid_amount) }}</dd>
      </div>
      <div class="flex justify-between gap-4">
        <dt class="font-bold text-slate-500">{{ $t('billing.console.print.remaining') }}</dt>
        <dd class="font-black" dir="ltr">{{ money(invoice.remaining) }}</dd>
      </div>
    </dl>

    <section v-if="invoice.payments?.length" class="mt-8 space-y-2">
      <h3 class="text-xs font-black">{{ $t('billing.console.print.payments') }}</h3>
      <table class="w-full text-xs text-start border-collapse">
        <thead>
          <tr class="bg-slate-100 text-slate-700">
            <th class="p-2 text-start font-black">{{ $t('billing.console.print.payment_date') }}</th>
            <th class="p-2 text-start font-black">{{ $t('billing.console.print.payment_method') }}</th>
            <th class="p-2 text-start font-black">{{ $t('billing.console.print.payment_amount') }}</th>
            <th class="p-2 text-start font-black">{{ $t('billing.console.print.payment_status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in invoice.payments" :key="payment.id" class="border-b border-slate-200">
            <td class="p-2">{{ formatDate(payment.paid_at || payment.submitted_at) }}</td>
            <td class="p-2 font-bold">{{ payment.method?.label }}</td>
            <td class="p-2 font-black" dir="ltr">{{ money(payment.amount) }}</td>
            <td class="p-2 font-bold">{{ payment.status?.label }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="invoice.notes" class="mt-6 text-xs">
      <h3 class="font-black">{{ $t('billing.console.print.notes') }}</h3>
      <p class="font-bold text-slate-600 whitespace-pre-line">{{ invoice.notes }}</p>
    </section>
  </article>
</template>

<style>
@media print {
  @page {
    size: A4;
    margin: 12mm;
  }

  html,
  body {
    background: #fff !important;
  }

  .invoice-sheet {
    max-width: none !important;
    box-shadow: none !important;
  }
}
</style>
