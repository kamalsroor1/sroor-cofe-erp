<script setup>
import { computed } from 'vue';
import { Info } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseButton from '../Common/BaseButton.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import { useTrans } from '../../Composables/useTrans';
import {
  STATUS_NOTE_MAX_LENGTH,
  TENANT_STATUS_TARGETS,
  TENANT_SUSPENSION_REASONS,
  TRIAL_EXTEND_MAX_DAYS,
  statusAcceptsExtendDays,
  statusAcceptsReason,
  statusRequiresReason,
} from '../../helpers/tenantStatusForm';

const props = defineProps({
  show: { type: Boolean, default: false },
  tenantName: { type: String, default: '' },
  currentStatus: { type: String, default: '' },
  form: { type: Object, default: () => ({ status: '', extend_days: 0, reason: '', note: '' }) },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit', 'update:field']);

const { t } = useTrans();

const statusOptions = computed(() =>
  TENANT_STATUS_TARGETS.map((value) => ({ value, label: t(`subscription.statuses.${value}`) }))
);

const reasonOptions = computed(() =>
  TENANT_SUSPENSION_REASONS.map((value) => ({ value, label: t(`subscription.suspension_reasons.${value}`) }))
);

const currentStatusLabel = computed(() =>
  props.currentStatus ? t(`subscription.statuses.${props.currentStatus}`) : ''
);

const showExtendDays = computed(() => statusAcceptsExtendDays(props.form.status));
const showReason = computed(() => statusAcceptsReason(props.form.status));
const reasonRequired = computed(() => statusRequiresReason(props.form.status));

const update = (field, value) => emit('update:field', field, value);
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('super.manage_tenant_status_modal_title')"
    :subtitle="tenantName"
    @close="$emit('close')"
  >
    <form class="space-y-4 font-tajawal" novalidate @submit.prevent="$emit('submit')">
      <div
        v-if="currentStatusLabel"
        class="flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
        data-testid="tenant-status-current"
      >
        <span class="font-bold">{{ $t('super.tenant_status.current_status') }}</span>
        <span
          class="inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-black border-[var(--color-primary-border)] bg-[var(--color-primary-light)] text-[var(--color-primary)]"
        >
          {{ currentStatusLabel }}
        </span>
      </div>

      <BaseSelect
        :model-value="form.status"
        :options="statusOptions"
        :label="$t('super.account_status_label')"
        :placeholder="$t('super.tenant_status.status_placeholder')"
        :error="errors.status || null"
        :searchable="false"
        required
        @update:model-value="update('status', $event)"
      />

      <p
        class="flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-xs font-bold text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200"
      >
        <Info class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
        <span>{{ $t('super.tenant_status.activation_hint') }}</span>
      </p>

      <BaseInput
        v-if="showExtendDays"
        :model-value="form.extend_days"
        :label="$t('super.tenant_status.trial_days_label')"
        :placeholder="$t('super.extend_days_placeholder')"
        :hint="$t('super.tenant_status.trial_days_hint')"
        :error="errors.extend_days || null"
        type="number"
        inputmode="numeric"
        min="0"
        :max="TRIAL_EXTEND_MAX_DAYS"
        input-class="font-mono"
        data-testid="tenant-status-extend-days"
        @update:model-value="update('extend_days', $event)"
      />

      <BaseSelect
        v-if="showReason"
        :model-value="form.reason"
        :options="reasonOptions"
        :label="$t('super.tenant_status.reason_label')"
        :placeholder="$t('super.tenant_status.reason_placeholder')"
        :error="errors.reason || null"
        :searchable="false"
        :required="reasonRequired"
        @update:model-value="update('reason', $event)"
      />

      <BaseTextarea
        :model-value="form.note"
        :label="$t('super.tenant_status.note_label')"
        :placeholder="$t('super.tenant_status.note_placeholder')"
        :error="errors.note || null"
        :maxlength="STATUS_NOTE_MAX_LENGTH"
        :rows="3"
        data-testid="tenant-status-note"
        @update:model-value="update('note', $event)"
      />

      <div
        class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-3 sm:flex-row sm:items-center sm:justify-end dark:border-slate-800"
      >
        <BaseButton type="button" variant="secondary" size="md" class="min-h-[44px]" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </BaseButton>
        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          class="min-h-[44px]"
          :loading="isSubmitting"
          :disabled="isSubmitting"
          data-testid="tenant-status-submit"
        >
          {{ $t('common.save') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>
