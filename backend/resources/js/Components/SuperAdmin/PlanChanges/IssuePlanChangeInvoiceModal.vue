<script setup>
import { computed, reactive, watch } from 'vue';
import { CheckCircle2, Printer } from 'lucide-vue-next';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import { useFormatters } from '../../../Composables/useFormatters';

const props = defineProps({
  request: { type: Object, default: null },
  plans: { type: Array, default: () => [] },
  plansFailed: { type: Boolean, default: false },
  issued: { type: Object, default: null },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const { formatMoney } = useFormatters();

const form = reactive({ amount: '', description: '', quantity: '1', unit_price: '', notes: '' });

const isAddon = computed(() => props.request?.kind.value === 'addon');
const cycleKey = computed(() => (props.request?.billing_cycle.value === 'yearly' ? 'price_yearly' : 'price_monthly'));
const priceOf = (ref) => {
  const plan = props.plans.find((p) => p.slug === ref?.code);
  return plan ? formatMoney(plan[cycleKey.value]) : null;
};
const currentPrice = computed(() => priceOf(props.request?.current_plan));
const requestedPrice = computed(() => priceOf(props.request?.requested_plan));

const firstError = (key) => props.errors[key]?.[0];

watch(
  () => props.request,
  (request) => {
    Object.assign(form, {
      amount: '',
      description: '',
      quantity: String(request?.quantity || 1),
      unit_price: '',
      notes: '',
    });
  }
);

const onSubmit = () => {
  const notes = form.notes.trim() || undefined;
  emit(
    'submit',
    isAddon.value
      ? {
          quantity: Number(form.quantity) || undefined,
          unit_price: form.unit_price.trim() || undefined,
          notes,
        }
      : { amount: form.amount.trim(), description: form.description.trim() || undefined, notes }
  );
};
</script>

<template>
  <AppModal
    :show="!!request"
    :title="$t('billing.console.plan_changes.issue.title')"
    :subtitle="request?.tenant?.name || ''"
    max-width="lg"
    @close="$emit('close')"
  >
    <div v-if="issued" class="space-y-4 text-center font-tajawal">
      <CheckCircle2 class="mx-auto h-12 w-12 text-emerald-500" />
      <h3 class="text-sm font-black text-slate-900 dark:text-white">
        {{ $t('billing.console.plan_changes.issue.done_title') }}
      </h3>
      <p class="text-xs font-bold text-slate-600 dark:text-slate-300" dir="ltr">
        {{ $t('billing.console.plan_changes.issue.done_text', { number: issued.number }) }}
      </p>
      <div class="flex flex-wrap justify-center gap-2">
        <BaseButton
          variant="primary"
          :icon="Printer"
          :to="`/super-admin/billing-invoices/${issued.id}/print`"
          :label="$t('billing.console.plan_changes.issue.done_open')"
        />
        <BaseButton
          variant="ghost"
          :label="$t('billing.console.plan_changes.issue.done_close')"
          @click="$emit('close')"
        />
      </div>
    </div>

    <form v-else-if="request" class="space-y-4 font-tajawal" novalidate @submit.prevent="onSubmit">
      <p class="text-sm font-black text-slate-900 dark:text-white">
        <template v-if="isAddon">
          {{
            $t('billing.console.plan_changes.addon_summary', {
              addon: request.addon?.name || '',
              quantity: request.quantity,
            })
          }}
        </template>
        <template v-else>
          {{
            $t('billing.console.plan_changes.plan_summary', {
              from: request.current_plan?.name || '-',
              to: request.requested_plan?.name || '-',
            })
          }}
        </template>
        - {{ request.billing_cycle.label }}
      </p>

      <template v-if="!isAddon">
        <div
          v-if="currentPrice || requestedPrice"
          class="space-y-1 rounded-2xl bg-slate-50 p-3 text-xs font-black text-slate-900 dark:bg-slate-800/60 dark:text-white"
        >
          <p v-if="currentPrice">
            {{
              $t('billing.console.plan_changes.issue.current_price', {
                plan: request.current_plan.name,
                amount: currentPrice,
              })
            }}
          </p>
          <p v-if="requestedPrice">
            {{
              $t('billing.console.plan_changes.issue.requested_price', {
                plan: request.requested_plan.name,
                amount: requestedPrice,
              })
            }}
          </p>
        </div>
        <p v-else-if="plansFailed" class="text-xs font-bold text-amber-600 dark:text-amber-400">
          {{ $t('billing.console.plan_changes.issue.prices_unknown') }}
        </p>

        <BaseInput
          v-model="form.amount"
          dir="ltr"
          inputmode="decimal"
          :label="$t('billing.console.plan_changes.issue.amount')"
          :hint="$t('billing.console.plan_changes.issue.amount_hint')"
          :error="firstError('amount')"
          required
        />
        <BaseInput
          v-model="form.description"
          maxlength="255"
          :label="$t('billing.console.plan_changes.issue.description')"
          :placeholder="$t('billing.console.plan_changes.issue.description_placeholder')"
          :error="firstError('description')"
        />
      </template>

      <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <BaseInput
          v-model="form.quantity"
          type="number"
          inputmode="numeric"
          min="1"
          max="50"
          dir="ltr"
          :label="$t('billing.console.plan_changes.issue.quantity')"
          :error="firstError('quantity')"
        />
        <BaseInput
          v-model="form.unit_price"
          dir="ltr"
          inputmode="decimal"
          :label="$t('billing.console.plan_changes.issue.unit_price')"
          :hint="$t('billing.console.plan_changes.issue.unit_price_hint')"
          :error="firstError('unit_price')"
        />
      </div>

      <BaseTextarea
        v-model="form.notes"
        :label="$t('billing.console.plan_changes.issue.notes')"
        :error="firstError('notes')"
        maxlength="1000"
        rows="2"
      />

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
          :label="$t('billing.console.plan_changes.issue.submit')"
        />
      </div>
    </form>
  </AppModal>
</template>
