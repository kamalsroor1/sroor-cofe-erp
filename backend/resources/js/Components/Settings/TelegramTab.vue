<script setup>
import { Send, BarChart3, AlertTriangle, RotateCcw, HardDrive, Save } from 'lucide-vue-next';
import { computed } from 'vue';
import BaseInput from '@/Components/Form/BaseInput.vue';

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits([
  'save',
  'send-test',
  'send-daily-summary',
  'send-low-stock',
  'send-overdue-shifts',
  'send-backup-telegram',
  'update:form',
]);

// Never mutate the prop: emit a patched shallow copy and let the parent apply it.
const updateForm = (key, value) => emit('update:form', { ...props.form, [key]: value });

// Writable computed keeps native checkbox v-model semantics.
const notificationsEnabled = computed({
  get: () => props.form.telegram_notifications_enabled,
  set: (value) => updateForm('telegram_notifications_enabled', value),
});
</script>

<template>
  <div class="space-y-6 font-tajawal">
    <form @submit.prevent="$emit('save')" class="space-y-6">
      <div
        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-6"
      >
        <div
          class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4 gap-3"
        >
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
              <Send class="w-4 h-4 text-theme-primary" />
              <span>{{ $t('settings.telegram_title') }}</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.telegram_sub') }}</p>
          </div>

          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              v-model="notificationsEnabled"
              class="w-4 h-4 rounded accent-theme-primary focus:ring-0"
            />
            <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $t('settings.enable_bot') }}</span>
          </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <BaseInput
            :model-value="form.telegram_bot_token"
            @update:model-value="updateForm('telegram_bot_token', $event)"
            :label="$t('settings.bot_token')"
            placeholder="123456789:ABCdef..."
            dir="ltr"
          />

          <BaseInput
            :model-value="form.telegram_chat_id"
            @update:model-value="updateForm('telegram_chat_id', $event)"
            :label="$t('settings.chat_id')"
            :placeholder="$t('settings.chat_id_placeholder')"
            dir="ltr"
          />
        </div>

        <!-- Live Action Testing Triggers -->
        <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-800">
          <h3 class="text-xs font-black text-theme-primary">{{ $t('settings.telegram_actions_title') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 sm:gap-3">
            <button
              @click="$emit('send-test')"
              type="button"
              class="min-h-[48px] p-3.5 rounded-2xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary text-slate-700 dark:text-slate-200 text-xs font-bold transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-xs"
            >
              <Send class="w-4 h-4 text-theme-primary" />
              <span>{{ $t('settings.send_test_msg') }}</span>
            </button>

            <button
              @click="$emit('send-daily-summary')"
              type="button"
              class="min-h-[48px] p-3.5 rounded-2xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary text-slate-700 dark:text-slate-200 text-xs font-bold transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-xs"
            >
              <BarChart3 class="w-4 h-4 text-emerald-500" />
              <span>{{ $t('settings.send_daily_summary') }}</span>
            </button>

            <button
              @click="$emit('send-low-stock')"
              type="button"
              class="min-h-[48px] p-3.5 rounded-2xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary text-slate-700 dark:text-slate-200 text-xs font-bold transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-xs"
            >
              <AlertTriangle class="w-4 h-4 text-rose-500" />
              <span>{{ $t('settings.send_low_stock') }}</span>
            </button>

            <button
              @click="$emit('send-overdue-shifts')"
              type="button"
              class="min-h-[48px] p-3.5 rounded-2xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary text-slate-700 dark:text-slate-200 text-xs font-bold transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-xs"
            >
              <RotateCcw class="w-4 h-4 text-theme-primary" />
              <span>{{ $t('settings.send_overdue_shifts') }}</span>
            </button>

            <button
              @click="$emit('send-backup-telegram')"
              type="button"
              class="min-h-[48px] p-3.5 rounded-2xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-theme-primary text-slate-700 dark:text-slate-200 text-xs font-bold transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer shadow-xs"
            >
              <HardDrive class="w-4 h-4 text-indigo-500" />
              <span>{{ $t('settings.send_backup_telegram') }}</span>
            </button>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800">
          <button
            type="submit"
            :disabled="form.processing"
            class="w-full sm:w-auto h-12 px-8 rounded-2xl btn-primary-theme font-black text-xs sm:text-sm transition transform active:scale-95 cursor-pointer disabled:opacity-50 flex items-center justify-center gap-2 shadow-theme-primary"
          >
            <Save class="w-4 h-4" />
            <span>{{ $t('settings.save_telegram_btn') }}</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
