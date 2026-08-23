<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-500 flex items-center justify-center">
          <Bot class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.telegram_section_title') }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.telegram_section_sub') }}</p>
        </div>
      </div>
    </div>

    <!-- Master Toggle -->
    <div
      @click="$emit('update:field', 'telegram_notifications_enabled', !form.telegram_notifications_enabled)"
      class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition select-none"
    >
      <div>
        <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.telegram_enable_toggle') }}</div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.telegram_enable_desc') }}</div>
      </div>
      <div
        class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
        :class="form.telegram_notifications_enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'"
      >
        <div
          class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
          :class="form.telegram_notifications_enabled ? '-translate-x-6' : 'translate-x-0'"
        ></div>
      </div>
    </div>

    <!-- Credentials -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <BaseInput
        :model-value="form.telegram_bot_token"
        @update:model-value="$emit('update:field', 'telegram_bot_token', $event)"
        :label="$t('settings.bot_token')"
        :placeholder="$t('settings.bot_token_placeholder')"
        dir="ltr"
      />

      <BaseInput
        :model-value="form.telegram_chat_id"
        @update:model-value="$emit('update:field', 'telegram_chat_id', $event)"
        :label="$t('settings.chat_id')"
        :placeholder="$t('settings.chat_id_input_placeholder')"
        dir="ltr"
      />
    </div>

    <!-- Test Notification Button -->
    <div class="pt-2 flex items-center justify-between p-4 rounded-2xl bg-cyan-500/10 border border-cyan-500/20">
      <div class="text-xs text-cyan-800 dark:text-cyan-200">
        <span class="font-bold block">{{ $t('settings.test_connection_title') }}</span>
        <span class="text-[11px] text-cyan-600 dark:text-cyan-400/80">{{ $t('settings.test_connection_desc') }}</span>
      </div>

      <button
        type="button"
        @click="$emit('test-telegram')"
        :disabled="isTesting || !form.telegram_chat_id"
        class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-cyan-600/20 flex items-center gap-2 transition active:scale-95 disabled:opacity-50 cursor-pointer"
      >
        <Send class="w-4 h-4" />
        <span>{{ isTesting ? $t('common.loading') : $t('settings.send_test_btn') }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { Bot, Send } from 'lucide-vue-next';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  form: { type: Object, default: () => ({}) },
  isTesting: { type: Boolean, default: false },
});

defineEmits(['update:field', 'test-telegram']);
</script>
