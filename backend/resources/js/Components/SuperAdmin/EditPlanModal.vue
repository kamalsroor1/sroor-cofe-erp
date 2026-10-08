<template>
  <AppModal
    :show="show"
    max-width="lg"
    :title="$t('super.edit_plan_modal_title', { name: form.name })"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-3.5 text-xs font-tajawal">
      <div>
        <BaseInput
          :model-value="form.name"
          @update:model-value="$emit('update:field', 'name', $event)"
          :label="$t('super.plan_name_label')"
          required
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseInput
            :model-value="form.price_monthly"
            @update:model-value="$emit('update:field', 'price_monthly', Number($event))"
            :label="$t('super.monthly_price_label')"
            type="number"
            step="0.01"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.price_yearly"
            @update:model-value="$emit('update:field', 'price_yearly', Number($event))"
            :label="$t('super.yearly_price_label')"
            type="number"
            step="0.01"
            class="font-mono"
            required
          />
        </div>
      </div>

      <fieldset class="space-y-2">
        <legend class="text-xs font-black text-slate-700 dark:text-slate-200 mb-2">
          {{ $t('super.plan_limits_title') }}
        </legend>
        <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
          {{ $t('super.plan_limits_hint') }}
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <BaseLimitInput
            v-for="field in limitFields"
            :key="field.key"
            :model-value="form[field.key]"
            :label="$t(field.formLabel)"
            :min="field.min"
            :error="limitErrorText(field.key)"
            @update:model-value="$emit('update:field', field.key, $event)"
          />
        </div>
      </fieldset>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
        <BaseCheckbox
          :model-value="form.is_active"
          @update:model-value="$emit('update:field', 'is_active', $event)"
          :label="$t('super.plan_active_checkbox')"
          wrapper-class="p-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
        />

        <BaseCheckbox
          :model-value="form.is_popular"
          @update:model-value="$emit('update:field', 'is_popular', $event)"
          :label="$t('super.popular_plan_checkbox')"
          wrapper-class="p-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
        />
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="secondary" size="md" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="isSubmitting"
          class="shadow-lg shadow-theme-primary font-black"
        >
          {{ isSubmitting ? $t('common.loading') : $t('common.save') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import BaseButton from '../Common/BaseButton.vue';
import BaseLimitInput from '../Form/BaseLimitInput.vue';
import { presentLimitFields } from '../../helpers/planLimits';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

const { t } = useTrans();

const limitFields = computed(() => presentLimitFields(props.form));

// Client errors are { min, max }; server (422) errors are string arrays.
const limitErrorText = (key) => {
  const error = props.errors?.[key];
  if (!error) return null;
  if (Array.isArray(error) || typeof error === 'string') return error;
  return t('super.limit_invalid', error);
};

defineEmits(['close', 'submit', 'update:field']);
</script>
