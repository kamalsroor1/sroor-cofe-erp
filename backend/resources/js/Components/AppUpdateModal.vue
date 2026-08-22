<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="isModalOpen"
        @click.self="closeModal"
        class="fixed inset-0 bg-white dark:bg-slate-900/90 backdrop-blur-sm z-[9999] flex items-center justify-center p-4 select-none font-tajawal"
        dir="rtl"
      >
        <Transition name="modal-zoom">
          <div
            v-if="isModalOpen"
            class="bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden text-slate-100 relative"
          >
            <!-- 🌟 State 1: Download Complete / Success State -->
            <div v-if="isDownloaded" class="p-6 text-center space-y-4">
              <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-emerald-500 to-teal-600 p-0.5 shadow-xl shadow-emerald-500/25 mx-auto flex items-center justify-center text-white">
                <CheckCircle class="w-8 h-8" />
              </div>

              <div class="space-y-1.5">
                <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $t('app_update.download_success_title') }}</h2>
                <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed px-2">
                  {{ $t('app_update.download_success_desc') }}
                </p>
              </div>

              <div class="p-3.5 rounded-2xl bg-slate-950/70 border border-slate-200 dark:border-slate-800 text-[11px] text-slate-400 text-start space-y-1">
                <div class="flex items-center gap-2 text-emerald-400 font-bold">
                  <Sparkles class="w-4 h-4" />
                  <span>{{ $t('app_update.install_steps_title') }}</span>
                </div>
                <p>{{ $t('app_update.install_step_1') }}</p>
                <p>{{ $t('app_update.install_step_2') }}</p>
              </div>

              <div class="pt-2 flex items-center gap-3">
                <button
                  type="button"
                  @click="closeModal"
                  class="flex-1 py-3 px-4 rounded-2xl bg-theme-gradient text-white shadow-theme-primary font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/25 transition active:scale-95 cursor-pointer"
                >
                  <Check class="w-4.5 h-4.5" />
                  <span>{{ $t('app_update.done_and_close') }}</span>
                </button>

                <button
                  type="button"
                  @click="startDownloadAndInstall"
                  class="py-3 px-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-white font-bold text-xs transition active:scale-95 cursor-pointer"
                  :title="$t('app_update.re_download')"
                >
                  {{ $t('app_update.re_download') }}
                </button>
              </div>
            </div>

            <!-- 🚀 State 2: Update Available & Downloading Details -->
            <template v-else>
              <!-- Top Gradient Accent Header -->
              <div class="p-6 bg-gradient-to-b from-amber-500/15 via-amber-500/5 to-transparent border-b border-slate-200 dark:border-slate-800 text-center relative">
                <!-- Close button (Only available if NOT forced) -->
                <button
                  v-if="!isForceUpdate"
                  type="button"
                  @click="closeModal"
                  class="absolute top-4 start-4 w-9 h-9 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition active:scale-90 cursor-pointer"
                  :title="$t('common.close')"
                >
                  <X class="w-4.5 h-4.5" />
                </button>

                <!-- Glowing Rocket Icon Badge -->
                <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-amber-500 to-amber-600 p-0.5 shadow-xl shadow-theme-primary mx-auto mb-3 flex items-center justify-center text-slate-950 animate-bounce">
                  <Rocket class="w-8 h-8" />
                </div>

                <h2 class="text-lg font-black text-slate-900 dark:text-white">
                  {{ isForceUpdate ? $t('app_update.mandatory_update_title') : $t('app_update.update_available_title') }}
                </h2>

                <!-- Version Comparison Badge -->
                <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 rounded-full bg-slate-800/90 border border-slate-700 text-xs font-bold">
                  <span class="text-slate-400">{{ $t('app_update.current_version') }} v{{ currentVersionName }}</span>
                  <span class="text-amber-400 font-mono">➔</span>
                  <span class="text-emerald-400 font-black">v{{ latestVersionData?.latest_version || '1.1.0' }}</span>
                </div>
              </div>

              <!-- Body Details -->
              <div class="p-6 space-y-4 text-xs">
                <!-- Force Update Notice -->
                <div v-if="isForceUpdate" class="p-3 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-start gap-2.5">
                  <AlertTriangle class="w-5 h-5 shrink-0 text-rose-400 mt-0.5" />
                  <div>
                    <div class="font-bold">{{ $t('app_update.security_update_badge') }}</div>
                    <p class="text-[11px] text-rose-300/80 mt-0.5 leading-relaxed">
                      {{ $t('app_update.mandatory_update_desc') }}
                    </p>
                  </div>
                </div>

                <!-- Metadata Chips -->
                <div class="flex items-center justify-between p-3 rounded-2xl bg-white dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 text-slate-400 text-[11px]">
                  <div class="flex items-center gap-1.5">
                    <HardDrive class="w-4 h-4 text-amber-400" />
                    <span>{{ $t('app_update.file_size') }}</span>
                    <span class="text-white font-bold">{{ latestVersionData?.file_size || '18.5 MB' }}</span>
                  </div>

                  <div class="flex items-center gap-1.5">
                    <Calendar class="w-4 h-4 text-emerald-400" />
                    <span>{{ $t('app_update.publish_date') }}</span>
                    <span class="text-white font-bold">{{ latestVersionData?.published_at?.split(' ')[0] || $t('app_update.today') }}</span>
                  </div>
                </div>

                <!-- Changelog / Release Notes -->
                <div>
                  <div class="font-bold text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                    <Sparkles class="w-4 h-4 text-amber-400" />
                    <span>{{ $t('app_update.changelog_title') }}</span>
                  </div>

                  <div class="p-3.5 rounded-2xl bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 text-slate-700 dark:text-slate-300 space-y-1.5 max-h-36 overflow-y-auto custom-scrollbar leading-relaxed">
                    <template v-if="latestVersionData?.release_notes?.length">
                      <div
                        v-for="(note, idx) in latestVersionData.release_notes"
                        :key="idx"
                        class="flex items-start gap-2 text-[11px]"
                      >
                        <span class="text-amber-400 shrink-0">•</span>
                        <span>{{ note }}</span>
                      </div>
                    </template>
                    <p v-else class="text-[11px] text-slate-400">
                      {{ latestVersionData?.release_notes_ar || $t('app_update.general_improvements') }}
                    </p>
                  </div>
                </div>

                <!-- Download Progress Bar (When Downloading) -->
                <div v-if="isDownloading" class="space-y-2 pt-2">
                  <div class="flex items-center justify-between text-[11px] font-bold">
                    <span class="text-amber-400 animate-pulse">{{ $t('app_update.downloading_package') }}</span>
                    <span class="text-white font-mono">{{ downloadProgress }}%</span>
                  </div>
                  <div class="w-full h-2.5 bg-slate-800 rounded-full overflow-hidden p-0.5 border border-slate-700">
                    <div
                      class="h-full bg-gradient-to-r from-amber-500 via-amber-400 to-emerald-500 rounded-full transition-all duration-150 shadow-md shadow-amber-500/50"
                      :style="{ width: `${downloadProgress}%` }"
                    ></div>
                  </div>
                </div>
              </div>

              <!-- Footer Action Buttons -->
              <div class="p-5 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/90 flex items-center gap-3">
                <button
                  type="button"
                  @click="startDownloadAndInstall"
                  :disabled="isDownloading"
                  class="flex-1 py-3 px-4 rounded-2xl bg-theme-gradient text-white font-black shadow-theme-primary text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-theme-primary transition active:scale-95 cursor-pointer disabled:opacity-50"
                >
                  <Download v-if="!isDownloading" class="w-4.5 h-4.5" />
                  <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  <span>{{ isDownloading ? $t('app_update.downloading', { progress: downloadProgress }) : $t('app_update.update_and_install_now') }}</span>
                </button>

                <button
                  v-if="!isForceUpdate && !isDownloading"
                  type="button"
                  @click="closeModal"
                  class="py-3 px-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-white font-bold text-xs transition active:scale-95 cursor-pointer"
                >
                  {{ $t('app_update.remind_later') }}
                </button>
              </div>
            </template>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { useAppUpdate } from '../Composables/useAppUpdate';
import {
    Rocket,
    X,
    AlertTriangle,
    HardDrive,
    Calendar,
    Sparkles,
    Download,
    CheckCircle,
    Check
} from 'lucide-vue-next';

const {
    currentVersionName,
    isForceUpdate,
    latestVersionData,
    isModalOpen,
    isDownloading,
    isDownloaded,
    downloadProgress,
    startDownloadAndInstall,
    closeModal
} = useAppUpdate();
</script>
