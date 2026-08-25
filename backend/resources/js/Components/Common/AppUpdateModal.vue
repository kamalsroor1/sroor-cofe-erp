<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="showUpdateModal"
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[99999] flex items-center justify-center p-4 font-tajawal"
        dir="rtl"
      >
        <div
          class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-5 relative overflow-hidden animate-in fade-in zoom-in duration-200"
        >
          <!-- Top Ambient Glow -->
          <div class="absolute -top-10 -right-10 w-32 h-32 bg-theme-primary/20 rounded-full blur-3xl pointer-events-none"></div>

          <!-- Header Icon & Title -->
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-theme-primary/10 text-theme-primary flex items-center justify-center text-2xl shrink-0">
              🚀
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="text-lg font-black text-slate-900 dark:text-white">
                {{ updateData?.title || $t('settings.new_update_available') }}
              </h3>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">الإصدار الجديد:</span>
                <span class="px-2 py-0.5 rounded-lg bg-emerald-500/10 text-emerald-500 font-mono font-bold text-xs">
                  v{{ updateData?.latest_version }}
                </span>
                <span class="text-xs font-mono text-slate-400">
                  ({{ updateData?.file_size || '8.6 MB' }})
                </span>
              </div>
            </div>
          </div>

          <!-- Release Notes -->
          <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200/80 dark:border-slate-800/80 space-y-2">
            <div class="text-xs font-bold text-slate-700 dark:text-slate-300">المميزات والتحديثات الجديدة:</div>
            <div v-if="updateData?.release_notes && updateData.release_notes.length > 0" class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
              <div
                v-for="(note, idx) in updateData.release_notes"
                :key="idx"
                class="text-xs text-slate-600 dark:text-slate-400 flex items-start gap-2"
              >
                <span class="text-emerald-500 font-black shrink-0">•</span>
                <span>{{ note }}</span>
              </div>
            </div>
            <div v-else class="text-xs text-slate-500 dark:text-slate-400">
              تحسينات في الأداء، إصلاحات في الواجهات، وزيادة استقرار النظام.
            </div>
          </div>

          <!-- Mandatory Alert (If Force Update) -->
          <div v-if="isForceUpdate" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-xs font-bold flex items-center gap-2">
            <span>⚠️</span>
            <span>هذا التحديث إلزامي لمواصلة استخدام المنظومة بأمان.</span>
          </div>

          <!-- Actions Footer -->
          <div class="flex items-center gap-3 pt-2">
            <button
              v-if="!isForceUpdate"
              type="button"
              @click="dismissUpdate"
              class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-2xl font-bold text-xs transition cursor-pointer active:scale-95"
            >
              {{ $t('common.remind_me_later') }}
            </button>
            <button
              type="button"
              @click="downloadAndInstall"
              class="flex-1 py-3 bg-theme-gradient text-white rounded-2xl font-black text-xs shadow-lg shadow-theme-primary/25 transition cursor-pointer active:scale-95 flex items-center justify-center gap-2"
            >
              <span>تحديث وتثبيت الآن 🚀</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { useAppUpdater } from '../../Composables/useAppUpdater';

const {
  showUpdateModal,
  isForceUpdate,
  updateData,
  downloadAndInstall,
  dismissUpdate,
} = useAppUpdater();
</script>
