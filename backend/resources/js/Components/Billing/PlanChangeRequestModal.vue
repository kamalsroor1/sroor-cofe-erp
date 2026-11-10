<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Info, Send } from 'lucide-vue-next';
import AppModal from '@/Components/Common/AppModal.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import BaseInput from '@/Components/Form/BaseInput.vue';
import BaseSelect from '@/Components/Form/BaseSelect.vue';
import BaseTextarea from '@/Components/Form/BaseTextarea.vue';
import SupportContact from './SupportContact.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps({
  show: { type: Boolean, default: false },
  plans: { type: Array, default: () => [] },
  addons: { type: Array, default: () => [] },
  hasPendingPlan: { type: Boolean, default: false },
  hasPendingAddon: { type: Boolean, default: false },
  support: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const { t } = useTrans();
const { formatMoney } = useFormatters();

const tab = ref('plan_change');
const form = reactive({ plan: '', addon: '', quantity: '1', cycle: 'monthly', notes: '' });

const plan = computed(() => props.plans.find((p) => p.code === form.plan) || null);
const addon = computed(() => props.addons.find((a) => a.key === form.addon) || null);
const isAddon = computed(() => tab.value === 'addon');
const blocked = computed(() => (isAddon.value ? props.hasPendingAddon : props.hasPendingPlan));
const options = computed(() => (isAddon.value ? props.addons : props.plans));

const planOptions = computed(() => props.plans.map((p) => ({ value: p.code, label: p.name })));
const addonOptions = computed(() => props.addons.map((a) => ({ value: a.key, label: a.name })));
const cycleOptions = computed(() =>
  ['monthly', 'yearly']
    .filter((cycle) => !(isAddon.value && cycle === 'yearly' && addon.value && addon.value.yearly_price == null))
    .map((value) => ({ value, label: t(`billing.billing_cycle.${value}`) }))
);

const estimate = computed(() => {
  if (!isAddon.value || !addon.value) return '';
  const unit = form.cycle === 'yearly' ? addon.value.yearly_price : addon.value.unit_price;
  const qty = Math.max(1, Number(form.quantity) || 1);
  return formatMoney(Number(unit) * qty);
});

const firstError = (key) => props.errors[key]?.[0];
const canSubmit = computed(() => !blocked.value && (isAddon.value ? !!form.addon : !!form.plan));

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    Object.assign(form, { plan: '', addon: '', quantity: '1', cycle: 'monthly', notes: '' });
    tab.value = props.hasPendingPlan && !props.hasPendingAddon ? 'addon' : 'plan_change';
  }
);

watch(
  () => form.addon,
  () => {
    if (!cycleOptions.value.some((o) => o.value === form.cycle)) form.cycle = 'monthly';
  }
);

const onSubmit = () => {
  if (!canSubmit.value) return;
  const notes = form.notes.trim() || undefined;
  emit(
    'submit',
    isAddon.value
      ? {
          kind: 'addon',
          addon_key: form.addon,
          quantity: Math.min(50, Math.max(1, Math.trunc(Number(form.quantity)) || 1)),
          billing_cycle: form.cycle,
          notes,
        }
      : { kind: 'plan_change', requested_plan_code: form.plan, billing_cycle: form.cycle, notes }
  );
};
</script>

<template>
  <AppModal :show="show" :title="$t('subscription.billing.requests.title')" max-width="lg" @close="$emit('close')">
    <form class="space-y-4 font-tajawal" novalidate @submit.prevent="onSubmit">
      <p
        class="flex items-start gap-2 rounded-2xl border border-sky-200 dark:border-sky-900/60 bg-sky-50 dark:bg-sky-950/40 p-3 text-xs font-bold leading-relaxed text-sky-700 dark:text-sky-300"
      >
        <Info class="w-4 h-4 shrink-0 mt-0.5" />
        <span>{{ $t('subscription.billing.requests.intro') }}</span>
      </p>

      <div
        role="tablist"
        class="flex gap-1.5 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800"
      >
        <button
          v-for="key in ['plan_change', 'addon']"
          :key="key"
          type="button"
          role="tab"
          :aria-selected="tab === key"
          class="min-h-[44px] flex-1 px-3 rounded-xl text-xs font-black transition cursor-pointer active:scale-[0.98]"
          :class="
            tab === key
              ? 'bg-white dark:bg-slate-900 text-theme-primary shadow-xs'
              : 'text-slate-600 dark:text-slate-400'
          "
          @click="tab = key"
        >
          {{ $t(`subscription.billing.requests.tabs.${key}`) }}
        </button>
      </div>

      <EmptyState
        v-if="!options.length"
        :icon="Info"
        :title="isAddon ? $t('subscription.billing.requests.no_addons') : $t('subscription.billing.requests.no_plans')"
      />

      <template v-else>
        <BaseSelect
          v-if="!isAddon"
          v-model="form.plan"
          :options="planOptions"
          :searchable="false"
          :label="$t('subscription.billing.requests.plan')"
          :placeholder="$t('subscription.billing.requests.plan_placeholder')"
          :error="firstError('requested_plan_code')"
          required
        />
        <BaseSelect
          v-else
          v-model="form.addon"
          :options="addonOptions"
          :searchable="false"
          :label="$t('subscription.billing.requests.addon')"
          :placeholder="$t('subscription.billing.requests.addon_placeholder')"
          :error="firstError('addon_key')"
          required
        />

        <dl
          v-if="plan"
          class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3 text-xs font-black text-slate-900 dark:text-white"
        >
          <dd>{{ $t('subscription.billing.requests.price_monthly', { amount: formatMoney(plan.price_monthly) }) }}</dd>
          <dd>{{ $t('subscription.billing.requests.price_yearly', { amount: formatMoney(plan.price_yearly) }) }}</dd>
        </dl>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <BaseInput
            v-if="isAddon"
            v-model="form.quantity"
            type="number"
            inputmode="numeric"
            min="1"
            max="50"
            dir="ltr"
            :label="$t('subscription.billing.requests.quantity')"
            :error="firstError('quantity')"
          />
          <BaseSelect
            v-model="form.cycle"
            :options="cycleOptions"
            :searchable="false"
            :label="$t('subscription.billing.requests.cycle')"
            :error="firstError('billing_cycle')"
            required
          />
        </div>

        <p v-if="estimate" class="text-xs font-black text-slate-900 dark:text-white">
          {{
            $t('subscription.billing.requests.addon_estimate', {
              amount: estimate,
              cycle: $t(`billing.billing_cycle.${form.cycle}`),
            })
          }}
        </p>
        <p v-if="plan || estimate" class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {{ $t('subscription.billing.requests.price_note') }}
        </p>

        <BaseTextarea
          v-model="form.notes"
          :label="$t('subscription.billing.requests.notes')"
          :placeholder="$t('subscription.billing.requests.notes_placeholder')"
          :error="firstError('notes')"
          maxlength="500"
          rows="2"
        />
      </template>

      <p v-if="blocked" class="text-xs font-bold text-amber-600 dark:text-amber-400" role="status">
        {{ $t('subscription.billing.requests.pending_blocked') }}
      </p>

      <SupportContact :support="support" />

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton type="button" variant="ghost" :label="$t('common.cancel')" @click="$emit('close')" />
        <BaseButton
          type="submit"
          variant="primary"
          :icon="Send"
          :loading="isSubmitting"
          :disabled="!canSubmit"
          :label="$t('subscription.billing.requests.submit')"
        />
      </div>
    </form>
  </AppModal>
</template>
