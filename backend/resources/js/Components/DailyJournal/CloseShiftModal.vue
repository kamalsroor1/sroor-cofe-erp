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
        :decimals="3"
        :suffix="$t('common.currency')"
        class="text-emerald-500 dark:text-emerald-400 text-lg font-bold"
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
        <BaseInput
          :model-value="form.notes"
          @update:model-value="$emit('update:field', 'notes', $event)"
          :label="$t('treasury.close_shift_notes_prompt')"
          :placeholder="$t('treasury.close_shift_notes_placeholder')"
        />
      </div>

      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton
          type="button"
          variant="secondary"
          size="md"
          @click="$emit('close')"
        >
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="submitting"
          class="font-black shadow-theme-primary shadow-lg"
        >
          {{ $t('treasury.confirm_close_shift_btn') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

const props = defineProps({
  show: { type: Boolean, default: false },
  shiftNumber: { type: [String, Number], default: '' },
  openingCashBalance: { type: Number, default: 0 },
  expectedCashInDrawer: { type: Number, default: 0 },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const diff = computed(() => {
  const actual = parseFloat(props.form.actual_cash_balance);
  if (isNaN(actual)) return 0;
  return actual - props.expectedCashInDrawer;
});

const diffClass = computed(() => {
  if (diff.value === 0) return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
  if (diff.value > 0) return 'bg-sky-500/10 border-sky-500/30 text-sky-600 dark:text-sky-400';
  return 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400';
});

const diffText = computed(() => {
  if (diff.value === 0) return `✓ ${t('treasury.drawer_balanced')}`;
  if (diff.value > 0) return `+${formatMoney(diff.value)} ${t('common.currency')} (${t('treasury.drawer_surplus')})`;
  return `${formatMoney(diff.value)} ${t('common.currency')} (${t('treasury.drawer_shortage')})`;
});
</script>
