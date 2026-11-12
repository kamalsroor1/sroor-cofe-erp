<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { KeyRound, Loader2, Lock, LogOut, Unlock } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import BaseInput from '../Form/BaseInput.vue';
import BasePasswordInput from '../Form/BasePasswordInput.vue';
import { useAuthStore } from '../../stores/auth';
import { useUnlockScreen } from '../../Composables/useUnlockScreen';

const authStore = useAuthStore();
const {
  hasPin,
  mode,
  secret,
  errorMessage,
  isSubmitting,
  isBlocked,
  isPinLocked,
  isConfirmingSwitch,
  isSwitching,
  canSubmit,
  setMode,
  submit,
  askSwitchUser,
  cancelSwitchUser,
  switchUser,
} = useUnlockScreen();

const panel = ref(null);

const focusSecret = async () => {
  await nextTick();
  panel.value?.querySelector('input')?.focus();
};

const onSubmit = async () => {
  const unlocked = await submit();
  if (!unlocked) focusSecret();
};

onMounted(focusSecret);
watch(mode, focusSecret);
</script>

<template>
  <div
    ref="panel"
    class="w-full max-w-sm space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900 sm:p-7"
  >
    <div class="flex flex-col items-center gap-3 text-center">
      <div
        class="flex h-14 w-14 items-center justify-center rounded-2xl border border-theme-border bg-theme-light text-theme-primary"
      >
        <Lock class="h-7 w-7" aria-hidden="true" />
      </div>
      <div class="space-y-1">
        <h2 id="screen-lock-title" class="text-lg font-black text-slate-900 dark:text-white">
          {{ $t('screen_lock.title') }}
        </h2>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
          {{ $t('screen_lock.subtitle', { name: authStore.userName }) }}
        </p>
      </div>
    </div>

    <div
      v-if="hasPin"
      class="grid grid-cols-2 gap-1 rounded-2xl bg-slate-100 p-1 dark:bg-slate-800"
      role="tablist"
      :aria-label="$t('screen_lock.method_label')"
    >
      <button
        type="button"
        role="tab"
        data-testid="screen-lock-tab-pin"
        :aria-selected="mode === 'pin'"
        :disabled="isSubmitting || isPinLocked"
        class="min-h-[44px] cursor-pointer rounded-xl text-xs font-black transition disabled:cursor-not-allowed"
        :class="
          mode === 'pin'
            ? 'bg-white text-theme-primary shadow-xs dark:bg-slate-900'
            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
        "
        @click="setMode('pin')"
      >
        {{ $t('screen_lock.mode_pin') }}
      </button>
      <button
        type="button"
        role="tab"
        data-testid="screen-lock-tab-password"
        :aria-selected="mode === 'password'"
        :disabled="isSubmitting"
        class="min-h-[44px] cursor-pointer rounded-xl text-xs font-black transition disabled:cursor-not-allowed"
        :class="
          mode === 'password'
            ? 'bg-white text-theme-primary shadow-xs dark:bg-slate-900'
            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
        "
        @click="setMode('password')"
      >
        {{ $t('screen_lock.mode_password') }}
      </button>
    </div>

    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
      <BaseInput
        v-if="mode === 'pin'"
        id="screen-lock-pin"
        v-model="secret"
        type="password"
        name="screen-lock-pin"
        :label="$t('screen_lock.pin_label')"
        :leading-icon="KeyRound"
        inputmode="numeric"
        autocomplete="off"
        :maxlength="6"
        dir="ltr"
        input-class="text-center tracking-[0.4em]"
        :disabled="isSubmitting"
        data-testid="screen-lock-pin"
      />
      <BasePasswordInput
        v-else
        id="screen-lock-password"
        v-model="secret"
        name="screen-lock-password"
        :label="$t('screen_lock.password_label')"
        autocomplete="current-password"
        :disabled="isSubmitting"
        data-testid="screen-lock-password"
      />

      <div
        v-if="errorMessage"
        role="alert"
        data-testid="screen-lock-error"
        class="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs font-bold text-rose-600 dark:text-rose-400"
      >
        {{ errorMessage }}
      </div>

      <BaseButton
        type="submit"
        variant="primary"
        size="lg"
        full-width
        :icon="isSubmitting ? Loader2 : Unlock"
        :loading="isSubmitting"
        :disabled="!canSubmit || isBlocked"
        data-testid="screen-lock-submit"
      >
        {{ $t('screen_lock.unlock') }}
      </BaseButton>
    </form>

    <div class="border-t border-slate-200 pt-4 dark:border-slate-800">
      <div v-if="isConfirmingSwitch" class="space-y-3" data-testid="screen-lock-switch-confirm">
        <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
          {{ $t('screen_lock.switch_user_warning') }}
        </p>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="min-h-[44px] cursor-pointer rounded-xl border border-slate-300 text-xs font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            :disabled="isSwitching"
            data-testid="screen-lock-switch-cancel"
            @click="cancelSwitchUser"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="button"
            class="min-h-[44px] cursor-pointer rounded-xl bg-rose-600 text-xs font-black text-white transition hover:bg-rose-700 disabled:opacity-50"
            :disabled="isSwitching"
            data-testid="screen-lock-switch-confirm-button"
            @click="switchUser"
          >
            {{ $t('screen_lock.switch_user_confirm') }}
          </button>
        </div>
      </div>
      <button
        v-else
        type="button"
        class="flex min-h-[44px] w-full cursor-pointer items-center justify-center gap-2 rounded-xl text-xs font-bold text-slate-500 transition hover:text-slate-800 disabled:opacity-50 dark:text-slate-400 dark:hover:text-slate-200"
        :disabled="isSubmitting"
        data-testid="screen-lock-switch-user"
        @click="askSwitchUser"
      >
        <LogOut class="h-4 w-4" aria-hidden="true" />
        <span>{{ $t('screen_lock.switch_user') }}</span>
      </button>
    </div>
  </div>
</template>
