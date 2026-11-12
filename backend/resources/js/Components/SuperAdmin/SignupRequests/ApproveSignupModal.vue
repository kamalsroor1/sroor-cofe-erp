<script setup>
import { Copy, RefreshCw, Loader2 } from 'lucide-vue-next';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseSelect from '../../Form/BaseSelect.vue';
import BaseCheckbox from '../../Form/BaseCheckbox.vue';
import TemporaryPasswordPanel from '../../Common/TemporaryPasswordPanel.vue';
import SignupApprovalTerms from './SignupApprovalTerms.vue';
import SignupMatchingTenants from './SignupMatchingTenants.vue';

defineProps({
  show: { type: Boolean, default: false },
  businessName: { type: String, default: '' },
  plans: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
  isPreparing: { type: Boolean, default: false },
  // Owner sign-in details returned once by the approval: { login, password, phone } or null.
  credentials: { type: Object, default: null },
  canWhatsapp: { type: Boolean, default: false },
  // What the shop will be created as (see helpers/signupApprovalPreview.js) or null.
  terms: { type: Object, default: null },
});

defineEmits(['close', 'submit', 'regenerate', 'copy', 'whatsapp']);

const form = defineModel('form', { type: Object, required: true });
</script>

<template>
  <AppModal
    :show="show"
    :title="$t('signup.console.approve_title')"
    :subtitle="businessName"
    :closeable="!credentials"
    @close="$emit('close')"
  >
    <div v-if="isPreparing" role="status" class="space-y-3 py-6 text-center">
      <Loader2 class="mx-auto h-9 w-9 animate-spin text-indigo-500" />
      <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('signup.console.preparing_title') }}</h3>
      <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
        {{ $t('signup.console.preparing_description') }}
      </p>
      <div v-if="credentials" class="space-y-3 border-t border-slate-200 pt-4 text-start dark:border-slate-800">
        <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('signup.console.credentials_title') }}</h4>
        <TemporaryPasswordPanel
          :password="credentials.password"
          :login="credentials.login"
          :can-whatsapp="canWhatsapp && !!credentials.phone"
          @whatsapp="$emit('whatsapp')"
        />
      </div>
      <BaseButton variant="secondary" size="md" data-testid="approve-modal-close" @click="$emit('close')">
        {{ $t('common.close') }}
      </BaseButton>
    </div>

    <form v-else class="space-y-4 text-xs" novalidate @submit.prevent="$emit('submit')">
      <BaseInput
        v-model="form.subdomain"
        :label="$t('signup.console.subdomain_label')"
        :error="errors.subdomain"
        dir="ltr"
        required
      />
      <BaseSelect
        v-model="form.plan_code"
        :options="plans"
        :label="$t('signup.console.plan_label')"
        :error="errors.plan_code"
        :searchable="false"
      />
      <SignupMatchingTenants v-if="terms?.matchingTenants.length" :tenants="terms.matchingTenants" />
      <SignupApprovalTerms v-if="terms" :terms="terms" />
      <BaseInput
        v-model="form.owner_email"
        type="email"
        :label="$t('signup.console.owner_email')"
        :error="errors.owner_email"
        dir="ltr"
        required
      />
      <div class="space-y-2">
        <BaseCheckbox
          v-model="form.generate_password"
          :label="$t('signup.console.password_mode_auto')"
          :description="$t('signup.console.password_mode_auto_hint')"
          data-testid="approve-auto-password"
        />
        <div v-if="!form.generate_password" class="space-y-1.5" data-testid="approve-manual-password">
          <BaseInput
            v-model="form.owner_password"
            :label="$t('signup.console.owner_password')"
            :hint="$t('signup.console.password_hint')"
            :error="errors.owner_password"
            autocomplete="new-password"
            dir="ltr"
          />
          <div class="flex flex-wrap gap-2">
            <BaseButton size="md" variant="secondary" :icon="RefreshCw" @click="$emit('regenerate')">
              {{ $t('signup.console.generate_password') }}
            </BaseButton>
            <BaseButton size="md" variant="secondary" :icon="Copy" @click="$emit('copy')">
              {{ $t('signup.console.copy_password') }}
            </BaseButton>
          </div>
        </div>
        <p v-else-if="errors.owner_password" class="text-xs font-bold text-rose-500">{{ errors.owner_password }}</p>
      </div>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-3 dark:border-slate-800">
        <BaseButton size="md" variant="secondary" @click="$emit('close')">{{ $t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" size="md" variant="success" :loading="isSubmitting">
          {{ $t('signup.console.approve_confirm') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>
