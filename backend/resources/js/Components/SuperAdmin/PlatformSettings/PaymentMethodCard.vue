<script setup>
import { computed } from 'vue';
import { Building2, Smartphone, Wallet } from 'lucide-vue-next';
import BaseInput from '../../Form/BaseInput.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import PaymentMethodPreview from './PaymentMethodPreview.vue';
import { METHOD_FIELDS, SETTING_LABEL_KEYS, methodStatus } from '../../../helpers/billingReview';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  method: { type: String, required: true },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
});

defineEmits(['update:field', 'focus-method']);

const { t } = useTrans();

const ICONS = { vodafone_cash: Smartphone, instapay: Wallet, bank_transfer: Building2 };
const fields = computed(() => METHOD_FIELDS[props.method] || []);
const status = computed(() => methodStatus(props.method, props.form));
const missingText = computed(() =>
  status.value.missing.map((key) => t(`billing.console.settings.${SETTING_LABEL_KEYS[key]}`)).join(', ')
);
const STATUS_CLASSES = {
  visible: 'text-emerald-800 dark:text-emerald-200',
  missing: 'text-amber-900 dark:text-amber-200',
  empty: 'text-slate-700 dark:text-slate-300',
};
const DOT_CLASSES = { visible: 'bg-emerald-500', missing: 'bg-amber-500', empty: 'bg-slate-400' };
</script>

<template>
  <section
    class="space-y-4 rounded-2xl border bg-white p-4 dark:bg-slate-900"
    :class="status.status === 'missing' ? 'border-amber-500' : 'border-slate-200 dark:border-slate-800'"
    :data-testid="`payment-method-${method}`"
    @focusin="$emit('focus-method', method)"
  >
    <header class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="flex items-center gap-2 text-base font-black text-slate-900 dark:text-white">
        <component :is="ICONS[method]" class="h-5 w-5 text-theme-primary" aria-hidden="true" />
        {{ $t(`billing.console.settings.method_${method}`) }}
      </h3>
      <p
        class="flex items-center gap-2 text-xs font-black"
        :class="STATUS_CLASSES[status.status]"
        role="status"
        :data-testid="`payment-method-status-${method}`"
      >
        <span class="h-2.5 w-2.5 rounded-full" :class="DOT_CLASSES[status.status]" aria-hidden="true"></span>
        <span v-if="status.status === 'visible'">{{ $t('billing.console.settings.status_visible') }}</span>
        <span v-else-if="status.status === 'missing'">{{
          $t('billing.console.settings.status_missing', { fields: missingText })
        }}</span>
        <span v-else>{{ $t('billing.console.settings.status_empty') }}</span>
      </p>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <template v-for="field in fields" :key="field.key">
        <BaseTextarea
          v-if="field.multiline"
          :model-value="form[field.key]"
          wrapper-class="sm:col-span-2"
          rows="2"
          :label="$t(field.label)"
          :placeholder="$t(field.placeholder)"
          :hint="$t(field.hint)"
          :error="errors[field.key]"
          :readonly="readonly"
          :maxlength="field.maxlength"
          @update:model-value="$emit('update:field', field.key, $event)"
        />
        <BaseInput
          v-else
          :model-value="form[field.key]"
          :type="field.type || 'text'"
          :dir="field.ltr ? 'ltr' : undefined"
          :inputmode="field.inputmode"
          :label="$t(field.label)"
          :placeholder="field.placeholder ? $t(field.placeholder) : ''"
          :required="field.required"
          :error="errors[field.key]"
          :readonly="readonly"
          :maxlength="field.maxlength"
          :data-testid="`setting-${field.key}`"
          @update:model-value="$emit('update:field', field.key, $event)"
        />
      </template>
    </div>

    <PaymentMethodPreview v-if="active" class="lg:hidden" :method="method" :form="form" />
  </section>
</template>
