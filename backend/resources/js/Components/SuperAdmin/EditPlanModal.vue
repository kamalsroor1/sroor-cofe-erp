<template>
  <AppModal
    :show="show"
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

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div>
          <BaseInput
            :model-value="form.max_users"
            @update:model-value="$emit('update:field', 'max_users', Number($event))"
            :label="$t('super.users_limit_label')"
            type="number"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.max_stores"
            @update:model-value="$emit('update:field', 'max_stores', Number($event))"
            :label="$t('super.stores_limit_label')"
            type="number"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.max_items"
            @update:model-value="$emit('update:field', 'max_items', Number($event))"
            :label="$t('super.items_limit_label')"
            type="number"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.max_invoices_per_month"
            @update:model-value="$emit('update:field', 'max_invoices_per_month', Number($event))"
            :label="$t('super.invoices_limit_label')"
            type="number"
            class="font-mono"
            required
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 pt-2">
        <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 cursor-pointer">
          <input
            type="checkbox"
            :checked="form.is_active"
            @change="$emit('update:field', 'is_active', $event.target.checked)"
            class="w-4 h-4 rounded text-theme-primary focus:ring-theme-primary"
          />
          <span class="text-slate-700 dark:text-slate-300 font-bold">{{ $t('super.plan_active_checkbox') }}</span>
        </label>

        <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 cursor-pointer">
          <input
            type="checkbox"
            :checked="form.is_popular"
            @change="$emit('update:field', 'is_popular', $event.target.checked)"
            class="w-4 h-4 rounded text-theme-primary focus:ring-theme-primary"
          />
          <span class="text-slate-700 dark:text-slate-300 font-bold">{{ $t('super.popular_plan_checkbox') }}</span>
        </label>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
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
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
