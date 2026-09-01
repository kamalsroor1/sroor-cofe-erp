<template>
  <Transition name="fade">
    <div
      v-if="selectedCount > 0"
      class="p-3 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white rounded-2xl border border-emerald-500/40 shadow-2xl flex flex-wrap items-center justify-between gap-3 animate-pulse-subtle"
    >
      <div class="flex items-center gap-2.5">
        <span class="w-7 h-7 rounded-lg bg-emerald-500 text-slate-950 font-black text-xs flex items-center justify-center shrink-0">
          {{ selectedCount }}
        </span>
        <span class="text-xs font-bold text-slate-200">
          {{ $t('invoices.selected_for_bulk') }}
        </span>
      </div>

      <div class="flex items-center gap-2 flex-wrap w-full sm:w-auto">
        <!-- Bulk Print Receipts -->
        <button
          type="button"
          @click="$emit('bulk-print')"
          class="flex-1 sm:flex-initial min-h-[40px] px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95 select-none"
        >
          <Printer class="w-3.5 h-3.5" />
          <span>{{ $t('invoices.bulk_print_receipts') }}</span>
        </button>

        <!-- Bulk Export to Excel -->
        <button
          type="button"
          @click="$emit('bulk-export')"
          class="flex-1 sm:flex-initial min-h-[40px] px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95 select-none"
        >
          <Download class="w-3.5 h-3.5" />
          <span>{{ $t('invoices.bulk_export_selected') }}</span>
        </button>

        <!-- Bulk Cancel Selected -->
        <button
          type="button"
          @click="$emit('bulk-cancel')"
          class="flex-1 sm:flex-initial min-h-[40px] px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95 select-none"
        >
          <Ban class="w-3.5 h-3.5" />
          <span>{{ $t('invoices.bulk_cancel_selected') }}</span>
        </button>

        <!-- Deselect All -->
        <button
          type="button"
          @click="$emit('deselect-all')"
          class="min-h-[40px] px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer active:scale-95 select-none"
        >
          {{ $t('invoices.deselect_all') }}
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { Printer, Download, Ban } from 'lucide-vue-next';

defineProps({
  selectedCount: { type: Number, default: 0 },
});

defineEmits(['bulk-print', 'bulk-export', 'bulk-cancel', 'deselect-all']);
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
