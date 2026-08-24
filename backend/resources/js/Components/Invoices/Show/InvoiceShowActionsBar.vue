<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm flex flex-wrap items-center justify-between gap-3 font-tajawal no-print">
    <!-- Print Options Group -->
    <div class="flex flex-wrap items-center gap-2">
      <BaseButton
        type="button"
        variant="primary"
        size="md"
        @click="$emit('print-thermal')"
        class="font-black bg-gradient-to-r from-emerald-500 to-teal-600 shadow-md shadow-emerald-500/20"
      >
        <Printer class="w-4 h-4" />
        <span>{{ $t('invoices.print_thermal_btn') }}</span>
      </BaseButton>

      <BaseButton
        type="button"
        variant="primary"
        size="md"
        @click="$emit('print-a4')"
        class="font-black bg-gradient-to-r from-sky-500 to-blue-600 shadow-md shadow-sky-500/20"
      >
        <FileText class="w-4 h-4" />
        <span>{{ $t('invoices.print_a4_btn') }}</span>
      </BaseButton>

      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        @click="$emit('whatsapp')"
        class="font-bold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
      >
        <MessageCircle class="w-4 h-4 text-emerald-500" />
        <span>{{ $t('invoices.share_whatsapp') }}</span>
      </BaseButton>

      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        @click="$emit('copy')"
        class="font-bold"
      >
        <Copy class="w-4 h-4" />
        <span>{{ $t('invoices.copy_details') }}</span>
      </BaseButton>
    </div>

    <!-- Management & Back Group -->
    <div class="flex items-center gap-2">
      <BaseButton
        v-if="!isCancelled"
        type="button"
        variant="danger"
        size="md"
        @click="$emit('open-cancel')"
        class="font-bold"
      >
        <Ban class="w-4 h-4" />
        <span>{{ $t('invoices.cancel_invoice_btn') }}</span>
      </BaseButton>

      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        @click="$emit('back')"
        class="font-bold"
      >
        <ArrowRight class="w-4 h-4" />
        <span>{{ $t('common.back') }}</span>
      </BaseButton>
    </div>
  </div>
</template>

<script setup>
import { Printer, FileText, MessageCircle, Copy, Ban, ArrowRight } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';

defineProps({
  isCancelled: { type: Boolean, default: false },
});

defineEmits(['print-thermal', 'print-a4', 'whatsapp', 'copy', 'open-cancel', 'back']);
</script>
