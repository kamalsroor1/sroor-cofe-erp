<template>
  <div
    class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm dark:shadow-xl space-y-4 text-xs font-tajawal"
  >
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-1.5">
          <KeyRound class="w-4 h-4 text-theme-primary" />
          <span>{{ $t('profile.pos_pin_title') }}</span>
        </h2>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $t('profile.pos_pin_hint') }}</p>
      </div>

      <span
        data-testid="profile-pos-pin-badge"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold border shrink-0"
        :class="
          hasPosPin
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
            : 'bg-slate-500/10 border-slate-500/30 text-slate-500 dark:text-slate-400'
        "
      >
        {{ hasPosPin ? $t('profile.pos_pin_set') : $t('profile.pos_pin_not_set') }}
      </span>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-3">
      <div>
        <BaseInput
          :model-value="pinForm.current_password"
          @update:model-value="pinForm.current_password = $event"
          :label="$t('profile.pos_pin_current_password_label')"
          type="password"
          placeholder="••••••••"
          class="font-mono"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <BaseInput
            :model-value="pinForm.pin"
            @update:model-value="pinForm.pin = $event"
            :label="$t('profile.pos_pin_input_label')"
            type="password"
            inputmode="numeric"
            maxlength="6"
            minlength="4"
            dir="ltr"
            placeholder="••••"
            class="font-mono"
          />
        </div>

        <div>
          <BaseInput
            :model-value="pinForm.pin_confirmation"
            @update:model-value="pinForm.pin_confirmation = $event"
            :label="$t('profile.pos_pin_confirm_label')"
            type="password"
            inputmode="numeric"
            maxlength="6"
            dir="ltr"
            placeholder="••••"
            class="font-mono"
          />
        </div>
      </div>

      <div v-if="validationError" class="text-xs text-rose-500 font-bold">
        {{ validationError }}
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <BaseButton
          v-if="hasPosPin"
          type="button"
          variant="danger"
          size="md"
          class="min-h-[44px]"
          :loading="submitting"
          @click="handleClear"
        >
          {{ $t('profile.pos_pin_clear_btn') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          class="min-h-[44px] shadow-lg shadow-theme-primary font-black"
          :loading="submitting"
        >
          {{ $t('profile.pos_pin_save_btn') }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { KeyRound } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  hasPosPin: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(['save-pin', 'clear-pin']);

const pinForm = ref({
  current_password: '',
  pin: '',
  pin_confirmation: '',
});

const validationError = ref('');

const handleSubmit = () => {
  validationError.value = '';

  const currentPassword = (pinForm.value.current_password || '').trim();
  const pin = (pinForm.value.pin || '').trim();
  const confirmation = (pinForm.value.pin_confirmation || '').trim();

  if (!currentPassword) {
    validationError.value = t('profile.pos_pin_current_password_required');
    return;
  }

  if (!/^\d{4,6}$/.test(pin)) {
    validationError.value = t('users.pos_pin_format');
    return;
  }

  if (pin !== confirmation) {
    validationError.value = t('profile.pos_pin_mismatch');
    return;
  }

  emit('save-pin', {
    pin,
    current_password: currentPassword,
  });

  pinForm.value = {
    current_password: '',
    pin: '',
    pin_confirmation: '',
  };
};

const handleClear = () => {
  validationError.value = '';
  const currentPassword = (pinForm.value.current_password || '').trim();

  if (!currentPassword) {
    validationError.value = t('profile.pos_pin_current_password_required');
    return;
  }

  emit('clear-pin', currentPassword);

  pinForm.value = {
    current_password: '',
    pin: '',
    pin_confirmation: '',
  };
};
</script>
