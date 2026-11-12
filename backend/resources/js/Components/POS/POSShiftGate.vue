<script setup>
import { Clock } from 'lucide-vue-next';
import POSShiftOpenForm from './POSShiftOpenForm.vue';

defineProps({
  openingCash: { type: String, default: '0' },
  canManage: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

const emit = defineEmits(['update:openingCash', 'submit', 'back']);
</script>

<template>
  <div
    class="absolute inset-0 z-20 flex items-center justify-center p-4 bg-slate-100/90 dark:bg-slate-950/90 backdrop-blur-xs"
    role="dialog"
    aria-modal="true"
    data-testid="pos-shift-gate"
  >
    <div
      class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl p-5 space-y-4"
    >
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center">
          <Clock class="w-6 h-6" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('pos.shift_gate_title') }}</h2>
          <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('pos.shift_gate_message') }}</p>
        </div>
      </div>

      <POSShiftOpenForm
        :opening-cash="openingCash"
        :can-manage="canManage"
        :is-submitting="isSubmitting"
        :error-message="errorMessage"
        @update:opening-cash="emit('update:openingCash', $event)"
        @submit="emit('submit')"
      />

      <button
        type="button"
        class="w-full min-h-[44px] px-4 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm font-bold cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition"
        data-testid="pos-shift-gate-back"
        @click="emit('back')"
      >
        {{ $t('common.back') }}
      </button>
    </div>
  </div>
</template>
