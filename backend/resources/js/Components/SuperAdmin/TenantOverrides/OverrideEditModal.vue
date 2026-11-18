<script setup>
import { computed } from 'vue';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseCheckbox from '../../Form/BaseCheckbox.vue';
import BaseTextarea from '../../Form/BaseTextarea.vue';
import BaseDatePicker from '../../Form/BaseDatePicker.vue';

/** One dialog for both kinds: a limit value (or unlimited) or a feature grant / revoke, plus reason and expiry. */
const props = defineProps({
  dialog: { type: Object, default: null }, // { kind: 'limit'|'feature', target, mode? }
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  minDate: { type: String, default: null },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const isLimit = computed(() => props.dialog?.kind === 'limit');
const title = computed(() => {
  if (isLimit.value) return props.dialog ? props.dialog.target.label : '';
  return props.dialog?.mode === 'revoke' ? 'revoke' : 'grant';
});
const firstError = (key) => props.errors[key]?.[0];
</script>

<template>
  <AppModal
    :show="!!dialog"
    :title="isLimit ? $t('plan_overrides.ui.edit') : $t(`plan_overrides.ui.feature_${title}_title`)"
    :subtitle="dialog?.target?.label || ''"
    max-width="lg"
    @close="$emit('close')"
  >
    <form
      v-if="dialog"
      class="space-y-4 font-tajawal"
      novalidate
      data-testid="override-form"
      @submit.prevent="$emit('submit')"
    >
      <template v-if="isLimit">
        <BaseInput
          :model-value="form.value"
          type="number"
          dir="ltr"
          inputmode="numeric"
          min="0"
          max="1000000"
          :disabled="form.unlimited"
          :label="$t('plan_overrides.ui.value')"
          :error="firstError('value')"
          @update:model-value="$emit('update:field', 'value', $event)"
        />
        <BaseCheckbox
          :model-value="form.unlimited"
          :label="$t('plan_overrides.ui.unlimited_checkbox')"
          @update:model-value="$emit('update:field', 'unlimited', $event)"
        />
      </template>
      <p v-else class="text-xs font-bold text-slate-500 dark:text-slate-400">
        {{ $t(`plan_overrides.ui.${title}_hint`) }}
      </p>

      <BaseTextarea
        :model-value="form.reason"
        :label="$t('plan_overrides.ui.reason')"
        :hint="$t('plan_overrides.ui.reason_hint')"
        :error="firstError('reason')"
        maxlength="255"
        rows="2"
        required
        @update:model-value="$emit('update:field', 'reason', $event)"
      />

      <BaseDatePicker
        :model-value="form.expires_at"
        :min-date="minDate"
        :label="$t('plan_overrides.ui.expires_at')"
        :hint="$t('plan_overrides.ui.expires_hint')"
        :error="firstError('expires_at')"
        @update:model-value="$emit('update:field', 'expires_at', $event || '')"
      />

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton
          type="button"
          variant="ghost"
          :label="$t('common.cancel')"
          :disabled="isSubmitting"
          @click="$emit('close')"
        />
        <BaseButton type="submit" variant="primary" :loading="isSubmitting" :label="$t('plan_overrides.ui.save')" />
      </div>
    </form>
  </AppModal>
</template>
