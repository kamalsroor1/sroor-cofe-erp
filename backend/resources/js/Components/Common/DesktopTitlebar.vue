<template>
  <header
    v-if="isDesktop"
    class="h-9 w-full shrink-0 sticky top-0 bg-slate-950 text-slate-300 border-b border-slate-800/80 flex items-center justify-between px-3 select-none text-xs font-tajawal z-[99999] drag-region"
    dir="rtl"
  >
    <!-- ☕ Right Side: App Brand & Active Store Context -->
    <div class="flex items-center gap-2.5 shrink-0 no-drag">
      <div class="flex items-center gap-1.5 font-black text-white text-xs">
        <span class="text-sm">☕</span>
        <span>{{ appConfigStore.companyName || $t('dashboard.company_title') }}</span>
      </div>

      <span class="w-1 h-3 bg-slate-800 rounded-full"></span>

      <!-- Active Branch Badge -->
      <div class="px-2 py-0.5 rounded-lg bg-theme-primary/10 border border-theme-primary/20 text-theme-primary text-[10px] font-bold flex items-center gap-1">
        <span>🏬</span>
        <span>{{ authStore.activeStoreName || 'الفرع الرئيسي' }}</span>
      </div>
    </div>

    <!-- 🟢 Center: Hardware & Server Ping Diagnostics Pill -->
    <div class="hidden md:flex items-center gap-3 text-[11px] font-mono text-slate-400 no-drag">
      <!-- Thermal Printer Status -->
      <button
        type="button"
        @click="$emit('open-hardware')"
        class="flex items-center gap-1.5 px-2 py-0.5 rounded-md hover:bg-slate-900 transition cursor-pointer"
        :title="$t('settings.desktop_hardware_title')"
      >
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span class="font-sans font-bold text-slate-300">🖨️ {{ activePrinterName }}</span>
      </button>

      <!-- Network Ping -->
      <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-900/80 border border-slate-800" :title="isOnline ? 'الاتصال بالسيرفر سليم ومستقر' : 'تعذر الاتصال بالسيرفر'">
        <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-500' : 'bg-rose-500 animate-ping'"></span>
        <span :class="isOnline ? 'text-slate-300 font-bold' : 'text-rose-400 font-bold'">
          {{ isOnline ? (serverPingMs ? `${serverPingMs}ms` : 'سحابي') : 'غير متصل' }}
        </span>
      </div>

      <!-- 🔄 Force Reload / Clear Cache -->
      <button
        type="button"
        @click="handleForceReload"
        class="flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-900/80 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-white transition cursor-pointer active:scale-95"
        title="تحديث فوري وتجاوز الكاش (Ctrl+Shift+R أو F5)"
      >
        <RefreshCw class="w-3 h-3 text-cyan-400" :class="{ 'animate-spin': isReloading }" />
        <span class="font-sans text-[10px] hidden lg:inline">تحديث الكاش</span>
      </button>
    </div>

    <!-- 🪟 Left Side (in RTL): Action Shortcuts & Native Window Controls -->
    <div class="flex items-center gap-1 shrink-0 no-drag">
      <!-- Quick POS Button (F2) -->
      <router-link
        to="/pos"
        class="hidden sm:flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 font-bold text-[11px] transition border border-emerald-500/20 active:scale-95"
        title="نقطة البيع السريعة (F2)"
      >
        <span>⚡ POS</span>
        <span class="font-mono text-[9px] bg-emerald-500/20 px-1 rounded">F2</span>
      </router-link>

      <!-- Keyboard Shortcuts Help Modal (F1) -->
      <button
        type="button"
        @click="$emit('open-shortcuts')"
        class="p-1.5 text-slate-400 hover:text-white hover:bg-slate-900 rounded-lg transition"
        title="دليل اختصارات الكيبورد (F1)"
      >
        <HelpCircle class="w-3.5 h-3.5" />
      </button>

      <!-- Sound Toggle -->
      <button
        type="button"
        @click="toggleSound"
        class="p-1.5 rounded-lg transition"
        :class="isSoundEnabled ? 'text-theme-primary hover:bg-slate-900' : 'text-slate-500 hover:bg-slate-900'"
        :title="isSoundEnabled ? 'كتم أصوات الكاشير' : 'تفعيل أصوات الكاشير'"
      >
        <Volume2 v-if="isSoundEnabled" class="w-3.5 h-3.5" />
        <VolumeX v-else class="w-3.5 h-3.5" />
      </button>

      <!-- Hardware Settings Trigger -->
      <button
        type="button"
        @click="$emit('open-hardware')"
        class="p-1.5 text-slate-400 hover:text-white hover:bg-slate-900 rounded-lg transition"
        :title="$t('settings.desktop_hardware_title')"
      >
        <Printer class="w-3.5 h-3.5" />
      </button>

      <!-- Fullscreen / Kiosk Toggle (F11) -->
      <button
        type="button"
        @click="toggleFullscreen"
        class="p-1.5 text-slate-400 hover:text-white hover:bg-slate-900 rounded-lg transition"
        title="الشاشة الكاملة (F11)"
      >
        <Maximize2 class="w-3.5 h-3.5" />
      </button>

      <span class="w-1 h-3 bg-slate-800 rounded-full mx-1"></span>

      <!-- Window Controls: Minimize, Maximize/Restore, Close -->
      <button
        type="button"
        @click="minimizeWindow"
        class="w-7 h-6 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 rounded transition cursor-pointer"
        title="تصغير (-)"
      >
        <Minus class="w-3.5 h-3.5" />
      </button>

      <button
        type="button"
        @click="maximizeWindow"
        class="w-7 h-6 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 rounded transition cursor-pointer"
        :title="isMaximized ? 'استعادة للحجم الطبيعي' : 'تكبير النافذة'"
      >
        <Copy v-if="isMaximized" class="w-3 h-3 rotate-180" />
        <Square v-else class="w-3 h-3" />
      </button>

      <button
        type="button"
        @click="closeWindow"
        class="w-7 h-6 flex items-center justify-center text-slate-400 hover:text-white hover:bg-rose-600 rounded transition cursor-pointer"
        title="إغلاق (Alt+F4)"
      >
        <X class="w-3.5 h-3.5" />
      </button>
    </div>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useDesktopHardware } from '../../Composables/useDesktopHardware';
import { useAudioFeedback } from '../../Composables/useAudioFeedback';
import { useAuthStore } from '../../stores/auth';
import { useAppConfigStore } from '../../stores/appConfig';
import {
  HelpCircle,
  Volume2,
  VolumeX,
  Printer,
  Maximize2,
  Minus,
  Square,
  Copy,
  X,
  RefreshCw
} from 'lucide-vue-next';

defineEmits(['open-hardware', 'open-shortcuts']);

const authStore = useAuthStore();
const appConfigStore = useAppConfigStore();
const isReloading = ref(false);

const {
  isDesktop,
  isMaximized,
  serverPingMs,
  isOnline,
  minimizeWindow,
  maximizeWindow,
  closeWindow,
  toggleFullscreen
} = useDesktopHardware();

const { isSoundEnabled, toggleSound } = useAudioFeedback();

const handleForceReload = async () => {
  isReloading.value = true;
  if (window.electronAPI?.clearCache) {
    await window.electronAPI.clearCache();
  } else if (window.electronAPI?.hardReload) {
    await window.electronAPI.hardReload();
  } else {
    window.location.reload();
  }
};

const activePrinterName = computed(() => {
  const saved = localStorage.getItem('desktop_thermal_printer');
  return saved && saved.trim() !== '' ? saved : 'طابعة افتراضية';
});
</script>

<style scoped>
.drag-region {
  -webkit-app-region: drag;
}
.no-drag {
  -webkit-app-region: no-drag;
}
</style>
