<template>
  <AppModal :show="show" :title="`${$t('treasury.close_shift')} - ${shiftNumber || ''}`" @close="$emit('close')">
    <form @submit.prevent="onSubmit" class="space-y-4 font-tajawal">
      <div v-if="loading" class="space-y-2" aria-busy="true" data-testid="close-shift-loading">
        <div class="h-5 rounded-lg bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
        <div class="h-5 rounded-lg bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
      </div>

      <div v-else-if="failed" class="space-y-3" role="alert" data-testid="close-shift-error">
        <p class="text-sm font-bold text-rose-600 dark:text-rose-400">{{ errorMessage }}</p>
        <BaseButton type="button" variant="secondary" size="md" class="min-h-[44px]" @click="$emit('retry')">
          {{ $t('common.retry') }}
        </BaseButton>
      </div>

      <template v-else>
        <div
          v-if="hasOpening"
          class="p-3.5 bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl"
        >
          <div class="flex items-center justify-between text-xs">
            <span class="text-slate-500 dark:text-slate-400 font-bold">{{
              $t('treasury.shift_opening_balance_label')
            }}</span>
            <span class="font-mono text-slate-900 dark:text-slate-200 font-bold"
              >{{ formatMoney(openingCashBalance) }} {{ currencyLabel }}</span
            >
          </div>
        </div>
        <!-- Blind count: no expected cash and no difference until the count is submitted -->
        <p class="text-xs font-bold text-slate-500 dark:text-slate-400" data-testid="close-shift-blind-hint">
          {{ $t('treasury.blind_count_hint') }}
        </p>
      </template>

      <BaseNumberInput
        :model-value="form.actual_cash_balance"
        @update:model-value="$emit('update:field', 'actual_cash_balance', $event)"
        :label="$t('treasury.actual_counted_cash_prompt')"
        :required="true"
        :disabled="loading || failed"
        :hint="canSubmit ? '' : $t('treasury.counted_cash_required')"
        :decimals="3"
        :suffix="currencyLabel"
        class="text-emerald-500 dark:text-emerald-400 text-lg font-bold"
      />

      <div>
        <BaseInput
          :model-value="form.notes"
          @update:model-value="$emit('update:field', 'notes', $event)"
          :label="$t('treasury.close_shift_notes_prompt')"
          :placeholder="$t('treasury.close_shift_notes_placeholder')"
        />
      </div>

      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="secondary" size="md" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="submitting"
          :disabled="!canSubmit"
          data-testid="close-shift-confirm"
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
import { isValidCountedCash } from '../../helpers/shiftCash';
import { hasMetric } from '../../helpers/dashboardMetrics';

const { formatMoney, currencyLabel } = useFormatters();

const props = defineProps({
  show: { type: Boolean, default: false },
  shiftNumber: { type: [String, Number], default: '' },
  openingCashBalance: { type: [String, Number], default: null },
  form: { type: Object, default: () => ({}) },
  submitting: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit', 'update:field', 'retry']);

const hasOpening = computed(() => hasMetric(props.openingCashBalance));

const canSubmit = computed(() => !props.loading && !props.failed && isValidCountedCash(props.form.actual_cash_balance));

const onSubmit = () => {
  if (canSubmit.value) emit('submit');
};
</script>
