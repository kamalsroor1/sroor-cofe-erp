<script setup>
import { Lock, Unlock } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { currencyLabel } = useFormatters();

defineProps({
  openingCash: { type: String, default: '0' },
  canManage: { type: Boolean, default: false },
  isSubmitting: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

const emit = defineEmits(['update:openingCash', 'submit']);
</script>

<template>
  <div
    v-if="!canManage"
    class="flex items-start gap-3 p-4 rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-300"
  >
    <Lock class="w-5 h-5 shrink-0 mt-0.5" />
    <p class="text-sm font-bold">{{ $t('pos.shift_gate_no_permission') }}</p>
  </div>

  <form v-else class="space-y-4" data-testid="pos-shift-open-form" @submit.prevent="emit('submit')">
    <div class="space-y-1.5">
      <label class="block text-xs font-bold text-slate-600 dark:text-slate-400" for="pos-shift-opening-cash">
        {{ $t('pos.shift_gate_opening_cash') }} ({{ currencyLabel }})
      </label>
      <input
        id="pos-shift-opening-cash"
        :value="openingCash"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        dir="ltr"
        class="w-full min-h-[48px] px-3.5 text-lg font-black font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 outline-hidden"
        @input="emit('update:openingCash', $event.target.value)"
      />
    </div>

    <p
      v-if="errorMessage"
      class="text-sm font-bold text-rose-600 dark:text-rose-400"
      role="alert"
      data-testid="pos-shift-error"
    >
      {{ errorMessage }}
    </p>

    <BaseButton type="submit" variant="primary" size="lg" class="w-full min-h-[48px]" :loading="isSubmitting">
      <Unlock class="w-5 h-5" />
      <span>{{ isSubmitting ? $t('pos.shift_gate_opening') : $t('pos.shift_gate_open') }}</span>
    </BaseButton>
  </form>
</template>
