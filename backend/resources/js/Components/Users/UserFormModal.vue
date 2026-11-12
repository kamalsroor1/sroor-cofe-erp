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
        :hint="isEditing ? $t('users.password_edit_flags_hint') : ''"
        type="password"
        autocomplete="new-password"
        :minlength="8"
        placeholder="••••••••"
      />

      <div class="pt-1">
        <BaseCheckbox
          :model-value="form.is_active"
          @update:model-value="$emit('update:field', 'is_active', $event)"
          :label="$t('users.account_active_login_checkbox')"
        />
      </div>

      <!-- POS Manager Approval PIN Section (Edit mode only) -->
      <div
        v-if="isEditing"
        class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 space-y-3"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <KeyRound class="w-4 h-4 text-theme-primary" />
            <span class="text-xs font-bold text-slate-900 dark:text-white">
              {{ $t('users.pos_pin_section_title') }}
            </span>
          </div>
          <span
            data-testid="user-pos-pin-badge"
            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border"
            :class="
              form.has_pos_pin
                ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
                : 'bg-slate-500/10 border-slate-500/30 text-slate-500 dark:text-slate-400'
            "
          >
            {{ form.has_pos_pin ? $t('users.pos_pin_set') : $t('users.pos_pin_not_set') }}
          </span>
        </div>

        <p class="text-[11px] text-slate-500 dark:text-slate-400">
          {{ $t('users.pos_pin_section_hint') }}
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <BaseInput
            :model-value="pinForm.pin"
            @update:model-value="pinForm.pin = $event"
            :label="$t('users.pos_pin_label')"
            type="password"
            autocomplete="new-password"
            inputmode="numeric"
            maxlength="6"
            minlength="4"
            dir="ltr"
            placeholder="••••"
            class="font-mono"
          />

          <BaseInput
            :model-value="pinForm.pin_confirmation"
            @update:model-value="pinForm.pin_confirmation = $event"
            :label="$t('users.pos_pin_confirm_label')"
            type="password"
            autocomplete="new-password"
            inputmode="numeric"
            maxlength="6"
            dir="ltr"
            placeholder="••••"
            class="font-mono"
          />
        </div>

        <div v-if="pinError" class="text-xs text-rose-500 font-bold">
          {{ pinError }}
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
          <BaseButton
            v-if="form.has_pos_pin"
            type="button"
            variant="danger"
            size="md"
            class="min-h-[44px]"
            @click="handleClearPin"
          >
            {{ $t('users.pos_pin_clear_btn') }}
          </BaseButton>

          <BaseButton
            type="button"
            variant="secondary"
            size="md"
            class="min-h-[44px]"
            :disabled="!pinForm.pin"
            @click="handleSavePin"
          >
            {{ $t('users.pos_pin_save_btn') }}
          </BaseButton>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="secondary" size="md" @click="$emit('close')">
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
import { ref, watch, computed } from 'vue';
import { KeyRound } from 'lucide-vue-next';
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

const emit = defineEmits(['close', 'submit', 'update:field', 'update-pos-pin', 'clear-pos-pin']);

const pinForm = ref({
  pin: '',
  pin_confirmation: '',
});
const pinError = ref('');

watch(
  () => props.show,
  (newVal) => {
    if (!newVal) {
      pinForm.value = { pin: '', pin_confirmation: '' };
      pinError.value = '';
    }
  }
);

const handleSavePin = () => {
  pinError.value = '';
  const pin = (pinForm.value.pin || '').trim();
  const confirmation = (pinForm.value.pin_confirmation || '').trim();

  if (!/^\d{6}$/.test(pin)) {
    pinError.value = t('users.pos_pin_format');
    return;
  }
  if (pin !== confirmation) {
    pinError.value = t('users.pos_pin_mismatch');
    return;
  }

  emit('update-pos-pin', pin);
};

/** Called by the parent after the server accepted the change, so a failed attempt keeps what was typed. */
const resetPin = () => {
  pinForm.value = { pin: '', pin_confirmation: '' };
  pinError.value = '';
};

defineExpose({ resetPin });

const handleClearPin = () => {
  emit('clear-pos-pin');
  pinError.value = '';
};

const formattedStores = computed(() => [
  { value: null, label: t('users.no_store_assigned') },
  ...props.storesList.map((st) => ({ value: st.id, label: st.name })),
]);
</script>
