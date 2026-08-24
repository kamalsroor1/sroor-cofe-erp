<template>
  <AppModal
    :show="show"
    :title="isEditing ? $t('users.edit_user_title') : $t('users.create_user_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <BaseInput
        :model-value="form.name"
        @update:model-value="$emit('update:field', 'name', $event)"
        :label="$t('users.fullname_label')"
        :required="true"
        :placeholder="$t('users.fullname_placeholder')"
      />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <BaseInput
          :model-value="form.phone"
          @update:model-value="$emit('update:field', 'phone', $event)"
          :label="$t('users.phone_label')"
          :required="true"
          dir="ltr"
          placeholder="010XXXXXXXX"
        />

        <BaseInput
          :model-value="form.email"
          @update:model-value="$emit('update:field', 'email', $event)"
          :label="$t('users.email_optional_label')"
          type="email"
          dir="ltr"
          placeholder="user@example.com"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseSelect
            :model-value="form.role"
            @update:model-value="$emit('update:field', 'role', $event)"
            :options="rolesList"
            value-key="id"
            label-key="name"
            :label="$t('users.job_role_label')"
            :searchable="false"
            required
          />
        </div>

        <div>
          <BaseSelect
            :model-value="form.default_store_id"
            @update:model-value="$emit('update:field', 'default_store_id', $event)"
            :options="formattedStores"
            :label="$t('users.default_store_label')"
            :searchable="false"
          />
        </div>
      </div>

      <BaseInput
        :model-value="form.password"
        @update:model-value="$emit('update:field', 'password', $event)"
        :label="isEditing ? $t('users.password_edit_label') : $t('users.password_create_label')"
        :required="!isEditing"
        type="password"
        placeholder="••••••••"
      />

      <div class="pt-1">
        <BaseCheckbox
          :model-value="form.is_active"
          @update:model-value="$emit('update:field', 'is_active', $event)"
          :label="$t('users.account_active_login_checkbox')"
        />
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
          :loading="submitting"
          class="font-black shadow-theme-primary shadow-lg"
        >
          {{ $t('common.save') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  show: { type: Boolean, default: false },
  isEditing: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  rolesList: { type: Array, default: () => [] },
  storesList: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const formattedStores = computed(() => [
  { value: null, label: t('users.no_store_assigned') },
  ...props.storesList.map(st => ({ value: st.id, label: st.name }))
]);
</script>
