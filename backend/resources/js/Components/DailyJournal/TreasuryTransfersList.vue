<template>
  <section
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal"
    data-testid="treasury-transfers"
  >
    <header
      class="flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-200 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-900/60"
    >
      <h3 class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white">
        <ArrowLeftRight class="w-4 h-4 text-theme-primary" />
        <span>{{ $t('treasury.transfer_history') }}</span>
        <span v-if="!loading" class="text-xs font-bold text-slate-500">({{ transfers.length }})</span>
      </h3>
      <BaseButton
        v-if="canTransfer"
        type="button"
        variant="primary"
        size="sm"
        class="min-h-[40px]"
        @click="$emit('new')"
      >
        {{ $t('treasury.transfer_action') }}
      </BaseButton>
    </header>

    <div v-if="loading" class="p-4">
      <TableSkeleton :rows="3" :cols="6" />
    </div>

    <div v-else-if="failed" class="p-4">
      <ErrorState :message="$t('treasury.transfers_load_failed')" @retry="$emit('retry')" />
    </div>

    <div v-else-if="transfers.length > 0">
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr
              class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800"
            >
              <th class="py-3 px-4 text-start font-bold">{{ $t('treasury.transfer_number') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('treasury.transfer_from_to') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('common.amount') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('treasury.transfer_fee_short') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('treasury.transfer_by') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.notes') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
            <tr
              v-for="tr in transfers"
              :key="tr.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
            >
              <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">{{ tr.transfer_number }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                {{ tr.from_label }}
                <ArrowRight
                  class="w-3.5 h-3.5 text-slate-400 mx-1 rtl:rotate-180 inline-block align-middle"
                  aria-hidden="true"
                />
                {{ tr.to_label }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black text-slate-900 dark:text-slate-100">
                {{ formatMoney(tr.amount) }} {{ currencyLabel }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono text-rose-500 dark:text-rose-400">
                {{ formatMoney(tr.transfer_fee) }}
              </td>
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">{{ tr.user_name || '—' }}</td>
              <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">{{ tr.notes || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="block md:hidden p-2 space-y-2">
        <div
          v-for="tr in transfers"
          :key="tr.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
              <span class="text-xs font-mono font-bold text-theme-primary">{{ tr.transfer_number }}</span>
              <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                {{ tr.from_label }}
                <ArrowRight
                  class="w-3.5 h-3.5 text-slate-400 rtl:rotate-180 inline-block align-middle"
                  aria-hidden="true"
                />
                {{ tr.to_label }}
              </h4>
            </div>
            <span class="text-sm font-black font-mono text-slate-900 dark:text-white whitespace-nowrap">
              {{ formatMoney(tr.amount) }} {{ currencyLabel }}
            </span>
          </div>
          <div
            class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500"
          >
            <span>{{ tr.user_name || '—' }}</span>
            <span v-if="Number(tr.transfer_fee) > 0" class="font-mono text-rose-500 dark:text-rose-400">
              {{ $t('treasury.transfer_fee_short') }}: {{ formatMoney(tr.transfer_fee) }}
            </span>
          </div>
          <p v-if="tr.notes" class="text-xs text-slate-500 dark:text-slate-400">{{ tr.notes }}</p>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('treasury.no_transfers_date')"
      :description="$t('treasury.no_transfers_date_desc')"
      :icon="ArrowLeftRight"
    />
  </section>
</template>

<script setup>
import { ArrowLeftRight, ArrowRight } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import ErrorState from '../Common/ErrorState.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, currencyLabel } = useFormatters();

defineProps({
  transfers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
  canTransfer: { type: Boolean, default: false },
});

defineEmits(['new', 'retry']);
</script>
