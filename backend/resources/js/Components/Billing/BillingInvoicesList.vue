<script setup>
import { Clock, Eye, FileText, Wallet } from 'lucide-vue-next';
import BaseButton from '@/Components/Common/BaseButton.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import BillingStatusBadge from './BillingStatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { invoiceTone, isPayable, lastRejection } from '@/helpers/billing';

defineProps({
  invoices: { type: Array, default: () => [] },
});

defineEmits(['details', 'pay']);

const { formatMoney, formatDate } = useFormatters();
</script>

<template>
  <section
    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal"
  >
    <header class="flex items-center gap-2.5">
      <FileText class="w-5 h-5 text-theme-primary" />
      <h2 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">
        {{ $t('subscription.billing.invoices.title') }}
      </h2>
    </header>

    <EmptyState
      v-if="!invoices.length"
      :icon="FileText"
      :title="$t('subscription.billing.invoices.empty_title')"
      :description="$t('subscription.billing.invoices.empty_description')"
    />

    <template v-else>
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs">
          <thead>
            <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="py-2 pe-3 text-start font-black">{{ $t('subscription.billing.invoices.number') }}</th>
              <th class="py-2 pe-3 text-start font-black">{{ $t('subscription.billing.invoices.type') }}</th>
              <th class="py-2 pe-3 text-start font-black">{{ $t('subscription.billing.invoices.status') }}</th>
              <th class="py-2 pe-3 text-start font-black">{{ $t('subscription.billing.invoices.total') }}</th>
              <th class="py-2 pe-3 text-start font-black">{{ $t('subscription.billing.invoices.remaining') }}</th>
              <th class="py-2 pe-3 text-start font-black">{{ $t('subscription.billing.invoices.due') }}</th>
              <th class="py-2 text-end font-black">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="invoice in invoices" :key="invoice.id" class="align-top">
              <td class="py-3 pe-3 font-black text-slate-900 dark:text-white whitespace-nowrap">
                {{ invoice.number }}
              </td>
              <td class="py-3 pe-3 text-slate-700 dark:text-slate-300">{{ invoice.type.label }}</td>
              <td class="py-3 pe-3">
                <div class="space-y-1.5">
                  <BillingStatusBadge :label="invoice.status.label" :tone="invoiceTone(invoice.status.value)" />
                  <p
                    v-if="invoice.has_pending_payment"
                    class="flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400"
                  >
                    <Clock class="w-3 h-3" />
                    {{ $t('subscription.billing.invoices.pending_review') }}
                  </p>
                  <p
                    v-if="lastRejection(invoice)"
                    class="text-[11px] font-bold text-rose-600 dark:text-rose-400 max-w-56"
                  >
                    {{ $t('subscription.billing.invoices.rejected_reason', { reason: lastRejection(invoice) }) }}
                  </p>
                </div>
              </td>
              <td class="py-3 pe-3 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                {{ formatMoney(invoice.total) }} {{ invoice.currency }}
              </td>
              <td class="py-3 pe-3 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                {{ formatMoney(invoice.remaining) }} {{ invoice.currency }}
              </td>
              <td class="py-3 pe-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                {{ invoice.due_at ? formatDate(invoice.due_at) : '-' }}
              </td>
              <td class="py-3">
                <div class="flex justify-end gap-2">
                  <BaseButton size="sm" variant="secondary" :icon="Eye" @click="$emit('details', invoice)">
                    {{ $t('subscription.billing.invoices.details') }}
                  </BaseButton>
                  <BaseButton
                    v-if="isPayable(invoice)"
                    size="sm"
                    variant="primary"
                    :icon="Wallet"
                    @click="$emit('pay', invoice)"
                  >
                    {{ $t('subscription.billing.invoices.pay') }}
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <ul class="md:hidden space-y-3">
        <li
          v-for="invoice in invoices"
          :key="invoice.id"
          class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3 space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="text-sm font-black text-slate-900 dark:text-white">{{ invoice.number }}</p>
              <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ invoice.type.label }}</p>
            </div>
            <BillingStatusBadge :label="invoice.status.label" :tone="invoiceTone(invoice.status.value)" />
          </div>
          <dl class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <dt class="font-bold text-slate-500 dark:text-slate-400">
                {{ $t('subscription.billing.invoices.total') }}
              </dt>
              <dd class="font-black text-slate-900 dark:text-white">
                {{ formatMoney(invoice.total) }} {{ invoice.currency }}
              </dd>
            </div>
            <div>
              <dt class="font-bold text-slate-500 dark:text-slate-400">
                {{ $t('subscription.billing.invoices.remaining') }}
              </dt>
              <dd class="font-black text-slate-900 dark:text-white">
                {{ formatMoney(invoice.remaining) }} {{ invoice.currency }}
              </dd>
            </div>
            <div class="col-span-2">
              <dt class="font-bold text-slate-500 dark:text-slate-400">
                {{ $t('subscription.billing.invoices.due') }}
              </dt>
              <dd class="font-black text-slate-900 dark:text-white">
                {{ invoice.due_at ? formatDate(invoice.due_at) : '-' }}
              </dd>
            </div>
          </dl>
          <p
            v-if="invoice.has_pending_payment"
            class="flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400"
          >
            <Clock class="w-3 h-3" />
            {{ $t('subscription.billing.invoices.pending_review') }}
          </p>
          <p v-if="lastRejection(invoice)" class="text-[11px] font-bold text-rose-600 dark:text-rose-400">
            {{ $t('subscription.billing.invoices.rejected_reason', { reason: lastRejection(invoice) }) }}
          </p>
          <div class="flex gap-2">
            <BaseButton class="flex-1" variant="secondary" :icon="Eye" @click="$emit('details', invoice)">
              {{ $t('subscription.billing.invoices.details') }}
            </BaseButton>
            <BaseButton
              v-if="isPayable(invoice)"
              class="flex-1"
              variant="primary"
              :icon="Wallet"
              @click="$emit('pay', invoice)"
            >
              {{ $t('subscription.billing.invoices.pay') }}
            </BaseButton>
          </div>
        </li>
      </ul>
    </template>
  </section>
</template>
