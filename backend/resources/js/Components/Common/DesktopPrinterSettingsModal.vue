<template>
  <AppModal
    :show="show"
    :title="$t('settings.desktop_hardware_title')"
    max-width="max-w-lg"
    @close="$emit('close')"
  >
    <div class="space-y-5 font-tajawal text-slate-800 dark:text-slate-100 p-1">
      <!-- 🖥️ Desktop App Header Banner -->
      <div class="p-3.5 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-theme-primary/10 text-theme-primary flex items-center justify-center text-xl">
            🖨️
          </div>
          <div>
            <div class="text-xs font-black text-slate-900 dark:text-white">
              {{ $t('settings.direct_thermal_printing') }}
            </div>
            <div class="text-[10px] text-slate-400 font-bold">
              {{ $t('settings.silent_print_desc') }}
            </div>
          </div>
        </div>
        <span class="px-2 py-1 rounded-lg bg-emerald-500/10 text-emerald-500 font-black text-[10px]">
          {{ $t('settings.desktop_ready') }}
        </span>
      </div>

      <!-- 1. Select Thermal Printer -->
      <div class="space-y-1.5">
        <label class="text-xs font-black text-slate-700 dark:text-slate-300">
          {{ $t('settings.default_thermal_printer') }}
        </label>
        <select
          v-model="selectedPrinter"
          @change="savePrinterSelection"
          class="w-full h-11 px-3 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary cursor-pointer font-tajawal shadow-xs"
        >
          <option value="">{{ $t('settings.system_default_printer') }}</option>
          <option v-for="p in availablePrinters" :key="p.name" :value="p.name">
            🖨️ {{ p.displayName || p.name }} {{ p.isDefault ? '(' + $t('settings.is_default_badge') + ')' : '' }}
          </option>
        </select>
        <p class="text-[10px] text-slate-400">
          {{ $t('settings.printer_auto_detected') }}
        </p>
      </div>

      <!-- 2. Paper Width Selection -->
      <div class="space-y-1.5">
        <label class="text-xs font-black text-slate-700 dark:text-slate-300">
          {{ $t('settings.paper_size_width') }}
        </label>
        <div class="grid grid-cols-2 gap-3">
          <button
            type="button"
            @click="setPaperWidth('80mm')"
            class="p-3 rounded-xl border text-xs font-black flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
            :class="paperWidth === '80mm'
              ? 'border-theme-primary bg-theme-primary/10 text-theme-primary'
              : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-600 dark:text-slate-400'"
          >
            <span>📄</span>
            <span>{{ $t('settings.paper_80mm') }}</span>
          </button>
          <button
            type="button"
            @click="setPaperWidth('58mm')"
            class="p-3 rounded-xl border text-xs font-black flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
            :class="paperWidth === '58mm'
              ? 'border-theme-primary bg-theme-primary/10 text-theme-primary'
              : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-600 dark:text-slate-400'"
          >
            <span>🧾</span>
            <span>{{ $t('settings.paper_58mm') }}</span>
          </button>
        </div>
      </div>

      <!-- 3. Hardware Test Actions -->
      <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 space-y-2">
        <div class="text-[11px] font-black text-slate-400 uppercase">
          {{ $t('settings.hardware_diagnostics') }}
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
          <!-- Test Print -->
          <button
            type="button"
            @click="handleTestPrint"
            :disabled="isPrinting"
            class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
          >
            <span>🖨️</span>
            <span>{{ isPrinting ? $t('settings.printing_in_progress') : $t('settings.test_thermal_print') }}</span>
          </button>

          <!-- Test Cash Drawer -->
          <button
            type="button"
            @click="handleTestDrawer"
            class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer"
          >
            <span>💵</span>
            <span>{{ $t('settings.test_kick_drawer') }}</span>
          </button>
        </div>
      </div>

      <!-- 4. Kiosk & Fullscreen Controls -->
      <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
        <div>
          <div class="text-xs font-black text-slate-900 dark:text-white">
            {{ $t('settings.kiosk_cashier_mode') }}
          </div>
          <div class="text-[10px] text-slate-400">
            {{ $t('settings.kiosk_cashier_desc') }}
          </div>
        </div>
        <button
          type="button"
          @click="toggleKiosk"
          class="px-3.5 py-1.5 rounded-xl text-xs font-black transition border active:scale-95 cursor-pointer"
          :class="isKioskMode
            ? 'bg-rose-500 text-white border-rose-600 shadow-md shadow-rose-500/20'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700'"
        >
          {{ isKioskMode ? $t('settings.kiosk_active') : $t('settings.kiosk_enable') }}
        </button>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <button
          type="button"
          @click="loadPrinters"
          class="px-3 py-2 rounded-xl text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer flex items-center gap-1.5"
        >
          <span>🔄</span>
          <span>{{ $t('settings.refresh_printers_list') }}</span>
        </button>
        <BaseButton
          variant="primary"
          @click="$emit('close')"
        >
          {{ $t('common.done') }}
        </BaseButton>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppModal from './AppModal.vue';
import BaseButton from './BaseButton.vue';
import { useDesktopHardware } from '../../Composables/useDesktopHardware';
import Swal from 'sweetalert2';
import { trans } from '../../helpers/trans';

defineProps({
  show: { type: Boolean, default: false }
});

defineEmits(['close']);

const {
  availablePrinters,
  isPrinting,
  isKioskMode,
  loadPrinters,
  printThermalReceipt,
  openCashDrawer,
  toggleKiosk
} = useDesktopHardware();

const selectedPrinter = ref(localStorage.getItem('desktop_thermal_printer') || '');
const paperWidth = ref(localStorage.getItem('desktop_paper_width') || '80mm');

const savePrinterSelection = () => {
  localStorage.setItem('desktop_thermal_printer', selectedPrinter.value);
};

const setPaperWidth = (width) => {
  paperWidth.value = width;
  localStorage.setItem('desktop_paper_width', width);
};

const handleTestPrint = async () => {
  const testSlipHtml = `
    <div style="text-align: center; font-family: sans-serif; font-size: 11px;">
      <h2 style="font-size: 14px; margin-bottom: 4px;">☕ سرور كوفي ERP</h2>
      <p style="font-size: 10px; margin-bottom: 8px;">تجربة الطباعة الحرارية المباشرة</p>
      <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
      <table style="width: 100%; font-size: 10px; text-align: right;">
        <tr><td>حالة الاتصال:</td><td style="text-align: left; font-weight: bold;">متصل ومطابق ✅</td></tr>
        <tr><td>عرض الورق:</td><td style="text-align: left;">${paperWidth.value}</td></tr>
        <tr><td>الوقت والتاريخ:</td><td style="text-align: left;">${new Date().toLocaleTimeString('ar-EG')}</td></tr>
      </table>
      <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
      <p style="font-size: 9px; margin-top: 6px;">تمت الطباعة الصامتة عبر Electron Desktop Shell بنجاح.</p>
    </div>
  `;

  const res = await printThermalReceipt(testSlipHtml, {
    printerName: selectedPrinter.value,
    paperWidth: paperWidth.value
  });

  if (res && res.success) {
    Swal.fire({
      icon: 'success',
      title: trans('settings.test_print_success'),
      timer: 2000,
      showConfirmButton: false
    });
  }
};

const handleTestDrawer = async () => {
  await openCashDrawer(selectedPrinter.value);
  Swal.fire({
    icon: 'info',
    title: trans('settings.drawer_signal_sent'),
    timer: 1500,
    showConfirmButton: false
  });
};

onMounted(() => {
  loadPrinters();
});
</script>
