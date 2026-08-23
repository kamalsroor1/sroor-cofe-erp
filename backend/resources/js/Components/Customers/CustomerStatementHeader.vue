<template>
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 no-print">
    <div class="space-y-1">
      <div class="flex items-center gap-3">
        <router-link
          to="/customers"
          class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-300 dark:border-slate-700 shrink-0"
          :title="$t('common.back')"
        >
          <ArrowRight class="w-5 h-5" />
        </router-link>
        <div>
          <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-1.5 font-tajawal">
            <span>{{ $t('contacts.ledger_title') }}:</span>
            <span class="text-theme-primary">{{ customerName || '—' }}</span>
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-bold font-tajawal">
            {{ $t('contacts.ledger_subtitle') }}
          </p>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2 self-end sm:self-auto">
      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        @click="$emit('print')"
        class="font-bold flex items-center gap-2"
      >
        <Printer class="w-4 h-4 text-theme-primary" />
        <span>{{ $t('common.print') }}</span>
      </BaseButton>
    </div>
  </div>
</template>

<script setup>
import { ArrowRight, Printer } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  customerName: { type: String, default: '' },
});

defineEmits(['print']);
</script>
