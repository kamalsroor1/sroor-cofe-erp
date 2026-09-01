<template>
  <AppModal
    :show="show"
    :title="$t('super.create_tenant_modal_title')"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-3.5 text-xs font-tajawal">
      <div>
        <BaseInput
          :model-value="form.name"
          @update:model-value="$emit('update:field', 'name', $event)"
          :label="$t('super.org_name_label')"
          :placeholder="$t('super.org_name_placeholder')"
          required
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseInput
            :model-value="form.slug"
            @update:model-value="$emit('update:field', 'slug', $event)"
            :label="$t('super.slug_label')"
            :placeholder="$t('super.slug_placeholder')"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseSelect
            :model-value="form.plan_id"
            @update:model-value="$emit('update:field', 'plan_id', Number($event))"
            :options="plansList"
            value-key="id"
            label-key="name"
            :label="$t('super.selected_plan_label')"
            :searchable="false"
            required
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseInput
            :model-value="form.email"
            @update:model-value="$emit('update:field', 'email', $event)"
            :label="$t('super.admin_email_label')"
            :placeholder="$t('super.admin_email_placeholder')"
            type="email"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.phone"
            @update:model-value="$emit('update:field', 'phone', $event)"
            :label="$t('super.admin_phone_label')"
            :placeholder="$t('super.admin_phone_placeholder')"
            type="tel"
            class="font-mono"
            required
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <BaseInput
            :model-value="form.password"
            @update:model-value="$emit('update:field', 'password', $event)"
            :label="$t('super.initial_password_label')"
            :placeholder="$t('super.initial_password_placeholder')"
            type="password"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.trial_days"
            @update:model-value="$emit('update:field', 'trial_days', Number($event))"
            :label="$t('super.trial_days_label')"
            type="number"
            min="0"
            class="font-mono"
          />
        </div>
      </div>

      <!-- Advanced Custom Database Settings Toggle -->
      <div class="pt-2">
        <button
          type="button"
          @click="showCustomDb = !showCustomDb"
          class="text-[11px] text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 font-bold flex items-center gap-1 cursor-pointer"
        >
          <span>{{ showCustomDb ? '▼ ' + $t('super.hide_custom_db_settings') : '▶ ' + $t('super.show_custom_db_settings') }}</span>
        </button>

        <div v-if="showCustomDb" class="mt-3 p-3.5 bg-slate-50 dark:bg-slate-900/80 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="text-[10px] text-amber-600 dark:text-amber-400 font-bold">
            {{ $t('super.custom_db_warning') }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <BaseInput
                :model-value="form.db_host"
                @update:model-value="$emit('update:field', 'db_host', $event)"
                :label="$t('super.db_host_label')"
                placeholder="127.0.0.1"
                class="font-mono"
              />
            </div>

            <div>
              <BaseInput
                :model-value="form.db_name"
                @update:model-value="$emit('update:field', 'db_name', $event)"
                :label="$t('super.db_name_label')"
                placeholder="tenant_custom_db"
                class="font-mono"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <BaseInput
                :model-value="form.db_username"
                @update:model-value="$emit('update:field', 'db_username', $event)"
                :label="$t('super.db_user_label')"
                placeholder="root"
                class="font-mono"
              />
            </div>

            <div>
              <BaseInput
                :model-value="form.db_password"
                @update:model-value="$emit('update:field', 'db_password', $event)"
                :label="$t('super.db_pass_label')"
                type="password"
              />
            </div>
          </div>
        </div>
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
          class="bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-black shadow-lg shadow-purple-500/20"
        >
          {{ isSubmitting ? $t('super.creating_org_status') : $t('super.create_and_provision_btn') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  plansList: { type: Array, default: () => [] },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'update:field']);

const showCustomDb = ref(false);
</script>
