<script setup>
import { computed } from 'vue';
import { Plus, Trash2 } from 'lucide-vue-next';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseSelect from '../../Form/BaseSelect.vue';
import BaseSwitch from '../../Form/BaseSwitch.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import { useTrans } from '../../../Composables/useTrans';
import { ISSUE_CYCLES, ISSUE_TYPES } from '../../../Composables/useIssueInvoice';

const props = defineProps({
  show: { type: Boolean, default: false },
  tenantName: { type: String, default: '' },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  plans: { type: Array, default: () => [] },
  isService: { type: Boolean, default: false },
  canAddLine: { type: Boolean, default: true },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit', 'update:field', 'add-line', 'remove-line', 'update:line']);

const { t } = useTrans();

const typeOptions = computed(() =>
  ISSUE_TYPES.map((value) => ({ value, label: t(`billing.billing_invoice_type.${value}`) }))
);
const cycleOptions = computed(() =>
  ISSUE_CYCLES.map((value) => ({ value, label: t(`billing.billing_cycle.${value}`) }))
);
const planOptions = computed(() => props.plans.map((plan) => ({ value: plan.id, label: plan.name })));

const update = (field, value) => emit('update:field', field, value);
const lineError = (index, key) => props.errors[`service_lines.${index}.${key}`]?.[0];
const firstError = (key) => props.errors[key]?.[0];
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('billing.console.issue.title')"
    :subtitle="tenantName"
    max-width="2xl"
    @close="$emit('close')"
  >
    <form class="space-y-4 font-tajawal" novalidate @submit.prevent="$emit('submit')">
      <BaseSelect
        :model-value="form.type"
        :options="typeOptions"
        :searchable="false"
        :label="$t('billing.console.issue.type')"
        :error="firstError('type')"
        @update:model-value="update('type', $event)"
      />

      <div v-if="!isService" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <BaseSelect
          :model-value="form.plan_id"
          :options="planOptions"
          :label="$t('billing.console.issue.plan')"
          :placeholder="$t('billing.console.issue.plan_placeholder')"
          :error="firstError('plan_id')"
          required
          @update:model-value="update('plan_id', $event)"
        />
        <BaseSelect
          :model-value="form.billing_cycle"
          :options="cycleOptions"
          :searchable="false"
          :label="$t('billing.console.issue.cycle')"
          :error="firstError('billing_cycle')"
          required
          @update:model-value="update('billing_cycle', $event)"
        />
        <BaseSwitch
          wrapper-class="sm:col-span-2"
          :model-value="form.use_founder_price"
          :label="$t('billing.console.issue.founder')"
          :description="$t('billing.console.issue.founder_hint')"
          @update:model-value="update('use_founder_price', $event)"
        />
      </div>

      <div v-else class="space-y-3">
        <h3 class="text-xs font-black text-slate-700 dark:text-slate-200">
          {{ $t('billing.console.issue.lines_title') }}
        </h3>
        <div
          v-for="(line, index) in form.service_lines"
          :key="index"
          class="grid grid-cols-[1fr_auto] sm:grid-cols-[1fr_9rem_auto] gap-2 items-start"
        >
          <BaseInput
            :model-value="line.description"
            :placeholder="$t('billing.console.issue.line_description')"
            :error="lineError(index, 'description')"
            maxlength="255"
            wrapper-class="col-span-2 sm:col-span-1"
            @update:model-value="$emit('update:line', index, 'description', $event)"
          />
          <BaseInput
            :model-value="line.amount"
            dir="ltr"
            inputmode="decimal"
            :placeholder="$t('billing.console.issue.line_amount')"
            :error="lineError(index, 'amount')"
            @update:model-value="$emit('update:line', index, 'amount', $event)"
          />
          <button
            type="button"
            :aria-label="$t('billing.console.issue.remove_line')"
            class="w-11 h-11 rounded-xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 flex items-center justify-center cursor-pointer active:scale-95"
            @click="$emit('remove-line', index)"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
        <p v-if="firstError('service_lines')" class="text-[11px] font-bold text-rose-500">
          {{ firstError('service_lines') }}
        </p>
        <BaseButton
          type="button"
          variant="outline"
          :icon="Plus"
          :disabled="!canAddLine"
          :label="canAddLine ? $t('billing.console.issue.add_line') : $t('billing.console.issue.max_lines')"
          @click="$emit('add-line')"
        />
      </div>

      <BaseInput
        :model-value="form.discount"
        dir="ltr"
        inputmode="decimal"
        :label="$t('billing.console.issue.discount')"
        :error="firstError('discount')"
        @update:model-value="update('discount', $event)"
      />

      <BaseTextarea
        :model-value="form.notes"
        :label="$t('billing.console.issue.notes')"
        :placeholder="$t('billing.console.issue.notes_placeholder')"
        :error="firstError('notes')"
        maxlength="1000"
        rows="2"
        @update:model-value="update('notes', $event)"
      />

      <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
        {{ $t('billing.console.issue.tax_notice') }}
      </p>

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton
          type="button"
          variant="ghost"
          :label="$t('common.cancel')"
          :disabled="isSubmitting"
          @click="$emit('close')"
        />
        <BaseButton
          type="submit"
          variant="primary"
          :loading="isSubmitting"
          :label="$t('billing.console.issue.submit')"
        />
      </div>
    </form>
  </AppModal>
</template>
