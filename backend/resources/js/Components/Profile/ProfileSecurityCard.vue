<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm dark:shadow-xl space-y-4 text-xs font-tajawal">
    <div>
      <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-1.5">
        <span>🔐</span>
        <span>{{ $t('profile.security_password_title') }}</span>
      </h2>
      <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $t('profile.password_leave_blank_hint') }}</p>
    </div>

    <div class="space-y-3">
      <div>
        <BaseInput
          :model-value="form.current_password"
          @update:model-value="$emit('update:field', 'current_password', $event)"
          :label="$t('profile.current_password')"
          type="password"
          placeholder="••••••••"
          class="font-mono"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <BaseInput
            :model-value="form.new_password"
            @update:model-value="$emit('update:field', 'new_password', $event)"
            :label="$t('profile.new_password')"
            type="password"
            placeholder="••••••••"
            class="font-mono"
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.new_password_confirmation"
            @update:model-value="$emit('update:field', 'new_password_confirmation', $event)"
            :label="$t('profile.confirm_new_password')"
            type="password"
            placeholder="••••••••"
            class="font-mono"
          />
        </div>
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
