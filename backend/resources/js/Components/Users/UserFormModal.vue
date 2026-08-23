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
          <label class="block text-slate-700 dark:text-slate-300 text-xs font-bold mb-1">{{ $t('users.job_role_label') }} <span class="text-rose-500">*</span></label>
          <select
            :value="form.role"
            @change="$emit('update:field', 'role', $event.target.value)"
            required
            class="w-full h-10 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-theme-primary font-tajawal cursor-pointer"
          >
            <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-slate-700 dark:text-slate-300 text-xs font-bold mb-1">{{ $t('users.default_store_label') }}</label>
          <select
            :value="form.default_store_id"
            @change="$emit('update:field', 'default_store_id', $event.target.value === '' ? null : $event.target.value)"
            class="w-full h-10 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-theme-primary font-tajawal cursor-pointer"
          >
            <option :value="null">{{ $t('users.no_store_assigned') }}</option>
            <option v-for="st in storesList" :key="st.id" :value="st.id">{{ st.name }}</option>
          </select>
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

      <div class="flex items-center gap-2 pt-1">
        <input
          :checked="form.is_active"
          @change="$emit('update:field', 'is_active', $event.target.checked)"
          type="checkbox"
          id="is_active_check"
          class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-theme-primary focus:ring-theme-primary cursor-pointer"
        />
        <label for="is_active_check" class="text-slate-700 dark:text-slate-300 text-xs font-bold cursor-pointer">
          {{ $t('users.account_active_login_checkbox') }}
        </label>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
        <button
          type="button"
          @click="$emit('close')"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-xs cursor-pointer"
        >
          {{ $t('common.cancel') }}
        </button>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="submitting"
          class="font-black shadow-theme-primary shadow-lg"
        >
          {{ isEditing ? $t('common.save') : $t('common.save') }}
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
  isEditing: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  rolesList: { type: Array, default: () => [] },
  storesList: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);
</script>
