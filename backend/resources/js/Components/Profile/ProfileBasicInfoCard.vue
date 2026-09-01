<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm dark:shadow-xl space-y-4 text-xs font-tajawal">
    <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5">
      <span>👤</span>
      <span>{{ $t('profile.basic_info') }}</span>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <BaseInput
          :model-value="form.name"
          @update:model-value="$emit('update:field', 'name', $event)"
          :label="$t('profile.full_name')"
          required
        />
      </div>

      <div>
        <BaseInput
          :model-value="form.phone"
          @update:model-value="$emit('update:field', 'phone', $event)"
          :label="$t('profile.phone_for_login')"
          class="font-mono"
          required
        />
      </div>

      <div class="sm:col-span-2">
        <BaseInput
          :model-value="form.email"
          @update:model-value="$emit('update:field', 'email', $event)"
          :label="$t('profile.email_optional')"
          type="email"
          class="font-mono"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  form: { type: Object, default: () => ({}) },
});

defineEmits(['update:field']);
</script>
