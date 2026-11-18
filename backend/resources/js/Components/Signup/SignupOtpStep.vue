<script setup>
import { ltrIsolate } from '@/helpers/bidi';
import { ref } from 'vue';
import { MailCheck } from 'lucide-vue-next';
import MarketingButton from '../Landing/MarketingButton.vue';
import SignupCodeInput from './SignupCodeInput.vue';

defineProps({
  emailMasked: { type: String, default: '' },
  codeError: { type: String, default: '' },
  isVerifying: { type: Boolean, default: false },
  isResending: { type: Boolean, default: false },
  /** mm:ss until a new code may be requested. */
  resendLabel: { type: String, default: '' },
  canResend: { type: Boolean, default: false },
});

const emit = defineEmits(['verify', 'resend', 'start-over']);

const code = ref('');
</script>

<template>
  <form
    class="mx-auto flex w-full max-w-[520px] flex-col gap-5 rounded-3xl border border-mk-line bg-mk-surface p-5 text-center mk-shadow-card sm:p-9"
    novalidate
    @submit.prevent="emit('verify', code)"
  >
    <span
      class="mx-auto inline-flex size-[72px] items-center justify-center rounded-full bg-mk-primary text-mk-primary-ink"
    >
      <MailCheck class="size-9" aria-hidden="true" />
    </span>
    <header class="flex flex-col gap-2">
      <h1 class="text-2xl font-bold text-mk-ink sm:text-[28px]">{{ $t('self_signup.ui.otp.title') }}</h1>
      <p class="text-base leading-[1.6] text-mk-muted">
        {{ $t('self_signup.ui.otp.subtitle', { email: ltrIsolate(emailMasked) }) }}
      </p>
    </header>

    <div class="text-start">
      <SignupCodeInput
        v-model="code"
        :label="$t('self_signup.ui.otp.code_label')"
        :error="codeError"
        :disabled="isVerifying"
        @complete="emit('verify', $event)"
      />
      <p class="mt-2 text-xs leading-[1.6] text-mk-faint">{{ $t('self_signup.ui.otp.wrong_help') }}</p>
    </div>

    <MarketingButton
      type="submit"
      size="lg"
      block
      :disabled="isVerifying || code.length !== 6"
      :aria-busy="isVerifying"
      class="disabled:cursor-not-allowed disabled:opacity-60"
    >
      {{ isVerifying ? $t('self_signup.ui.otp.verifying') : $t('self_signup.ui.otp.submit') }}
    </MarketingButton>

    <div class="flex flex-col items-center gap-1">
      <p class="text-sm text-mk-muted">{{ $t('self_signup.ui.otp.check_spam') }}</p>
      <button
        type="button"
        class="inline-flex min-h-11 cursor-pointer items-center rounded-lg px-3 text-sm font-bold text-mk-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-mk-primary disabled:cursor-not-allowed disabled:no-underline disabled:opacity-60"
        :disabled="!canResend || isResending"
        @click="emit('resend')"
      >
        <template v-if="isResending">{{ $t('self_signup.ui.otp.resending') }}</template>
        <template v-else-if="!canResend">
          {{ $t('self_signup.ui.otp.resend_in', { time: resendLabel }) }}
        </template>
        <template v-else>{{ $t('self_signup.ui.otp.resend') }}</template>
      </button>
      <button
        type="button"
        class="inline-flex min-h-11 cursor-pointer items-center rounded-lg px-3 text-sm font-medium text-mk-muted hover:underline focus-visible:outline-2 focus-visible:outline-mk-primary"
        @click="emit('start-over')"
      >
        {{ $t('self_signup.ui.otp.start_over') }}
      </button>
    </div>
  </form>
</template>
