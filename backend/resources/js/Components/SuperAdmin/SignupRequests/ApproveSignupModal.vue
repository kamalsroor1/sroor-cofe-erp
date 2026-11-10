<script setup>
import { Copy, RefreshCw, Loader2 } from 'lucide-vue-next';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import BaseInput from '../../Form/BaseInput.vue';
import BaseSelect from '../../Form/BaseSelect.vue';

defineProps({
  show: { type: Boolean, default: false },
  businessName: { type: String, default: '' },
  plans: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
  isPreparing: { type: Boolean, default: false },
});

defineEmits(['close', 'submit', 'regenerate', 'copy']);

const form = defineModel('form', { type: Object, required: true });
</script>

<template>
  <AppModal :show="show" :title="$t('signup.console.approve_title')" :subtitle="businessName" @close="$emit('close')">
    <div v-if="isPreparing" role="status" class="space-y-3 py-6 text-center">
      <Loader2 class="mx-auto h-9 w-9 animate-spin text-indigo-500" />
      <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('signup.console.preparing_title') }}</h3>
      <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
        {{ $t('signup.console.preparing_description') }}
      </p>
      <BaseButton variant="secondary" size="md" @click="$emit('close')">{{ $t('common.close') }}</BaseButton>
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
      <BaseInput
        v-model="form.owner_email"
        type="email"
        :label="$t('signup.console.owner_email')"
        :error="errors.owner_email"
        dir="ltr"
        required
      />
      <div class="space-y-1.5">
        <BaseInput
          v-model="form.owner_password"
          :label="$t('signup.console.owner_password')"
          :hint="$t('signup.console.password_hint')"
          :error="errors.owner_password"
          autocomplete="new-password"
          dir="ltr"
          required
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

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-3 dark:border-slate-800">
        <BaseButton size="md" variant="secondary" @click="$emit('close')">{{ $t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" size="md" variant="success" :loading="isSubmitting">
          {{ $t('signup.console.approve_confirm') }}
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>
