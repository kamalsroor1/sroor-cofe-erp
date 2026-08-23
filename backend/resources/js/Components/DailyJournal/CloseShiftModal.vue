<template>
  <AppModal
    :show="show"
    :title="`${$t('treasury.close_shift')} - ${shiftNumber || ''}`"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <div class="p-3.5 bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('treasury.shift_opening_balance_label') }}</span>
          <span class="font-mono text-slate-900 dark:text-slate-200 font-bold">{{ formatMoney(openingCashBalance) }} {{ $t('common.currency') }}</span>
        </div>
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('treasury.shift_expected_balance_label') }}</span>
          <span class="font-mono text-theme-primary font-black text-sm">{{ formatMoney(expectedCashInDrawer) }} {{ $t('common.currency') }}</span>
        </div>
      </div>

      <BaseNumberInput
        :model-value="form.actual_cash_balance"
        @update:model-value="$emit('update:field', 'actual_cash_balance', $event)"
        :label="$t('treasury.actual_counted_cash_prompt')"
        :required="true"
        step="0.001"
        :suffix="$t('common.currency')"
        input-class="text-emerald-500 dark:text-emerald-400 text-lg font-bold"
      />

      <!-- Live Discrepancy Preview -->
      <div
        v-if="form.actual_cash_balance !== ''"
        class="p-3 rounded-xl border text-xs font-bold flex items-center justify-between"
        :class="diffClass"
      >
        <span>{{ $t('treasury.drawer_difference_label') }}</span>
        <span class="font-mono text-sm">{{ diffText }}</span>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
          {{ $t('treasury.close_shift_notes_prompt') }}
        </label>
        <input
          :value="form.notes"
          @input="$emit('update:field', 'notes', $event.target.value)"
          type="text"
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
          :placeholder="$t('treasury.close_shift_notes_placeholder')"
        >
      </div>

      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <button
          type="button"
          @click="$emit('close')"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold cursor-pointer"
        >
          {{ $t('common.cancel') }}
        </button>

        <BaseButton
          type="submit"
          variant="danger"
          size="md"
          :loading="submitting"
          class="font-black shadow-rose-500/20 shadow-lg"
        >
          {{ $t('treasury.confirm_close_zreport_btn') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

const props = defineProps({
  show: { type: Boolean, default: false },
  shiftNumber: { type: String, default: '' },
  openingCashBalance: { type: Number, default: 0 },
  expectedCashInDrawer: { type: Number, default: 0 },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const diff = computed(() => {
  const actual = parseFloat(props.form.actual_cash_balance) || 0;
  const expected = parseFloat(props.expectedCashInDrawer) || 0;
  return actual - expected;
});

const diffText = computed(() => {
  if (Math.abs(diff.value) < 0.001) return t('treasury.exact_match_no_diff');
  if (diff.value > 0) return t('treasury.drawer_overage', { amount: diff.value.toFixed(2) });
  return t('treasury.drawer_shortage', { amount: Math.abs(diff.value).toFixed(2) });
});

const diffClass = computed(() => {
  if (Math.abs(diff.value) < 0.001) return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
  if (diff.value > 0) return 'bg-theme-light border-theme-border text-theme-primary';
  return 'bg-rose-500/10 border-rose-500/30 text-rose-500 dark:text-rose-400';
});
</script>
