<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm flex flex-wrap items-center justify-between gap-3 font-tajawal no-print">
    <!-- Print & Share Options Group -->
    <div class="flex flex-wrap items-center gap-2.5">
      <!-- Print Thermal 80mm -->
      <BaseButton
        type="button"
        variant="success"
        size="md"
        :icon="Printer"
        :label="$t('invoices.print_thermal_btn')"
        @click="$emit('print-thermal')"
      />

      <!-- Print A4 Official Document -->
      <BaseButton
        type="button"
        variant="primary"
        size="md"
        :icon="FileText"
        :label="$t('invoices.print_a4_btn')"
        @click="$emit('print-a4')"
      />

      <!-- WhatsApp Share -->
      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        :icon="MessageCircle"
        icon-class="text-emerald-500 dark:text-emerald-400"
        :label="$t('invoices.share_whatsapp')"
        @click="$emit('whatsapp')"
      />

      <!-- Copy Details -->
      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        :icon="Copy"
        icon-class="text-slate-500 dark:text-slate-400"
        :label="$t('invoices.copy_details')"
        @click="$emit('copy')"
      />
    </div>

    <!-- Management & Back Group -->
    <div class="flex items-center gap-2.5">
      <!-- Cancel Invoice (Safe Stock Reversal) -->
      <BaseButton
        v-if="!isCancelled"
        type="button"
        variant="danger"
        size="md"
        :icon="Ban"
        :label="$t('invoices.cancel_invoice_btn')"
        @click="$emit('open-cancel')"
      />

      <!-- Back Button -->
      <BaseButton
        type="button"
        variant="secondary"
        size="md"
        :icon="ArrowRight"
        icon-class="rtl:rotate-180"
        :label="$t('common.back')"
        @click="$emit('back')"
      />
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
