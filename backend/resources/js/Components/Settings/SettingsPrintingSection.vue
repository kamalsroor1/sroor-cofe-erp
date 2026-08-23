<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-sm dark:shadow-xl space-y-6 font-tajawal">
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-500 flex items-center justify-center">
          <Printer class="w-5 h-5" />
        </div>
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('settings.printing_section_title') }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('settings.printing_section_sub') }}</p>
        </div>
      </div>
    </div>

    <!-- Toggles List -->
    <div class="space-y-3">
      <div
        @click="$emit('update:field', 'show_print_company_name', !form.show_print_company_name)"
        class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition select-none"
      >
        <div>
          <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.print_company_name_toggle') }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.print_company_name_desc') }}</div>
        </div>
        <div
          class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
          :class="form.show_print_company_name ? 'bg-theme-primary' : 'bg-slate-300 dark:bg-slate-700'"
        >
          <div
            class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
            :class="form.show_print_company_name ? '-translate-x-6' : 'translate-x-0'"
          ></div>
        </div>
      </div>

      <div
        @click="$emit('update:field', 'show_print_subtitle', !form.show_print_subtitle)"
        class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition select-none"
      >
        <div>
          <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.print_subtitle_toggle') }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.print_subtitle_desc') }}</div>
        </div>
        <div
          class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
          :class="form.show_print_subtitle ? 'bg-theme-primary' : 'bg-slate-300 dark:bg-slate-700'"
        >
          <div
            class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
            :class="form.show_print_subtitle ? '-translate-x-6' : 'translate-x-0'"
          ></div>
        </div>
      </div>

      <div
        @click="$emit('update:field', 'thermal_show_customer_balance', !form.thermal_show_customer_balance)"
        class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition select-none"
      >
        <div>
          <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.thermal_balance_toggle') }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.thermal_balance_desc') }}</div>
        </div>
        <div
          class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
          :class="form.thermal_show_customer_balance ? 'bg-theme-primary' : 'bg-slate-300 dark:bg-slate-700'"
        >
          <div
            class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
            :class="form.thermal_show_customer_balance ? '-translate-x-6' : 'translate-x-0'"
          ></div>
        </div>
      </div>

      <div
        @click="$emit('update:field', 'print_show_qr', !form.print_show_qr)"
        class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 cursor-pointer transition select-none"
      >
        <div>
          <div class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $t('settings.print_qr_toggle') }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('settings.print_qr_desc') }}</div>
        </div>
        <div
          class="w-12 h-6 rounded-full transition-colors relative p-0.5 shrink-0"
          :class="form.print_show_qr ? 'bg-theme-primary' : 'bg-slate-300 dark:bg-slate-700'"
        >
          <div
            class="w-5 h-5 rounded-full bg-white transition-transform shadow-md"
            :class="form.print_show_qr ? '-translate-x-6' : 'translate-x-0'"
          ></div>
        </div>
      </div>
    </div>

    <!-- Footer Note -->
    <div class="space-y-1.5 pt-2">
      <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('settings.invoice_footer_note_label') }}</label>
      <textarea
        :value="form.invoice_footer_note"
        @input="$emit('update:field', 'invoice_footer_note', $event.target.value)"
        rows="3"
        :placeholder="$t('settings.invoice_footer_placeholder')"
        class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary rounded-2xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:outline-none transition leading-relaxed"
      ></textarea>
    </div>
  </div>
</template>

<script setup>
import { Printer } from 'lucide-vue-next';

defineProps({
  form: { type: Object, default: () => ({}) },
});

defineEmits(['update:field']);
</script>
