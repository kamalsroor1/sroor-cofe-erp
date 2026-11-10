<script setup>
import AppModal from '../Common/AppModal.vue';
import ErrorState from '../Common/ErrorState.vue';
import EmptyState from '../Common/EmptyState.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

defineProps({
  show: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  lines: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
  total: { type: [String, Number], default: '0' },
  canSubmit: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'set-quantity', 'submit']);

const { formatMoney, formatQty } = useFormatters();
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('pos.quick_return_title', { number: invoice?.invoice_number || '' })"
    max-width="lg"
    @close="$emit('close')"
  >
    <div class="p-4 space-y-3 font-tajawal select-none">
      <div v-if="isLoading" class="space-y-2">
        <Skeleton v-for="n in 3" :key="n" height="h-16" />
      </div>

      <ErrorState v-else-if="errorMessage" compact :can-retry="false" :message="errorMessage" />

      <EmptyState v-else-if="!lines.length" :title="$t('pos.quick_return_nothing')" />

      <template v-else>
        <ul class="space-y-2 max-h-[50vh] overflow-y-auto">
          <li
            v-for="line in lines"
            :key="line.id"
            class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center gap-3"
          >
            <div class="flex-1 min-w-0 text-start">
              <div class="text-sm font-black text-slate-900 dark:text-white truncate">{{ line.item_name }}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">
                {{ $t('pos.quick_return_remaining', { qty: formatQty(line.remaining_quantity) }) }}
                · {{ formatMoney(line.unit_price) }}
              </div>
            </div>
            <input
              type="number"
              inputmode="decimal"
              min="0"
              step="any"
              dir="ltr"
              :value="line.return_quantity"
              :aria-label="$t('pos.quick_return_qty')"
              :placeholder="$t('pos.quick_return_qty')"
              class="w-24 min-h-[44px] px-2 text-center font-mono font-bold rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
              @input="$emit('set-quantity', line, $event.target.value, $event.target)"
            />
          </li>
        </ul>

        <div
          class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-black text-slate-900 dark:text-white"
        >
          <span>{{ $t('pos.quick_return_total') }}</span>
          <span class="font-mono">{{ formatMoney(total) }} {{ $t('common.currency') }}</span>
        </div>
      </template>

      <div class="flex gap-2">
        <button
          type="button"
          class="min-h-[44px] flex-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-black cursor-pointer"
          @click="$emit('close')"
        >
          {{ $t('common.cancel') }}
        </button>
        <button
          type="button"
          class="min-h-[44px] flex-1 rounded-xl bg-amber-500 text-slate-950 text-xs font-black cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="!canSubmit"
          @click="$emit('submit')"
        >
          {{ isSubmitting ? $t('pos.quick_return_submitting') : $t('pos.quick_return_confirm') }}
        </button>
      </div>
    </div>
  </AppModal>
</template>
