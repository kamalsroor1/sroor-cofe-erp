<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="isModalOpen"
        @click.self="closeModal"
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[9999] flex items-center justify-center p-4 select-none font-tajawal"
        dir="rtl"
      >
        <Transition name="modal-zoom">
          <div
            v-if="isModalOpen"
            class="bg-slate-900 border border-slate-700 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden text-slate-100 relative"
          >
            <!-- 🌟 State 1: Download Complete / Success State -->
            <div v-if="isDownloaded" class="p-6 text-center space-y-4">
              <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-emerald-500 to-teal-600 p-0.5 shadow-xl shadow-emerald-500/25 mx-auto flex items-center justify-center text-white">
                <CheckCircle class="w-8 h-8" />
              </div>

              <div class="space-y-1.5">
                <h2 class="text-lg font-black text-white">تم تنزيل حزمة التحديث بنجاح! 🎉</h2>
                <p class="text-xs text-slate-300 leading-relaxed px-2">
                  تم تنزيل ملف <span class="text-amber-400 font-mono font-bold">APK</span> على جهازك. يرجى فتح الإشعار أو الضغط على ملف التثبيت للمتابعة.
                </p>
              </div>

              <div class="p-3.5 rounded-2xl bg-slate-950/70 border border-slate-800 text-[11px] text-slate-400 text-start space-y-1">
                <div class="flex items-center gap-2 text-emerald-400 font-bold">
                  <Sparkles class="w-4 h-4" />
                  <span>خطوات تثبيت الـ APK على الأندرويد:</span>
                </div>
                <p>1. اضغط على ملف التنزيل في إشعارات الهاتف.</p>
                <p>2. اختر <strong>تثبيت (Install)</strong> عند ظهور رسالة النظام.</p>
              </div>

              <div class="pt-2 flex items-center gap-3">
                <button
                  type="button"
                  @click="closeModal"
                  class="flex-1 py-3 px-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/25 transition active:scale-95 cursor-pointer"
                >
                  <Check class="w-4.5 h-4.5" />
                  <span>تم وإغلاق النافذة</span>
                </button>

                <button
                  type="button"
                  @click="startDownloadAndInstall"
                  class="py-3 px-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs transition active:scale-95 cursor-pointer"
                  title="إعادة التنزيل"
                >
                  إعادة التنزيل
                </button>
              </div>
            </div>

            <!-- 🚀 State 2: Update Available & Downloading Details -->
            <template v-else>
              <!-- Top Gradient Accent Header -->
              <div class="p-6 bg-gradient-to-b from-amber-500/15 via-amber-500/5 to-transparent border-b border-slate-800 text-center relative">
                <!-- Close button (Only available if NOT forced) -->
                <button
                  v-if="!isForceUpdate"
                  type="button"
                  @click="closeModal"
                  class="absolute top-4 start-4 w-9 h-9 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition active:scale-90 cursor-pointer"
                  title="إغلاق"
                >
                  <X class="w-4.5 h-4.5" />
                </button>

                <!-- Glowing Rocket Icon Badge -->
                <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-amber-500 to-amber-600 p-0.5 shadow-xl shadow-amber-500/25 mx-auto mb-3 flex items-center justify-center text-slate-950 animate-bounce">
                  <Rocket class="w-8 h-8" />
                </div>

                <h2 class="text-lg font-black text-white">
                  {{ isForceUpdate ? 'تحديث إلزامي جديد للتطبيق' : 'يتوفر تحديث جديد للتطبيق 🚀' }}
                </h2>

                <!-- Version Comparison Badge -->
                <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 rounded-full bg-slate-800/90 border border-slate-700 text-xs font-bold">
                  <span class="text-slate-400">الإصدار الحالي: v{{ currentVersionName }}</span>
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
                    <div class="font-bold">تحديث أمني إلزامي</div>
                    <p class="text-[11px] text-rose-300/80 mt-0.5 leading-relaxed">
                      يتضمن هذا الإصدار تعديلات هيكلية وأمنية هامة تتطلب التحديث للاستمرار في استخدام النظام.
                    </p>
                  </div>
                </div>

                <!-- Metadata Chips -->
                <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-950/60 border border-slate-800 text-slate-400 text-[11px]">
                  <div class="flex items-center gap-1.5">
                    <HardDrive class="w-4 h-4 text-amber-400" />
                    <span>حجم الملف:</span>
                    <span class="text-white font-bold">{{ latestVersionData?.file_size || '18.5 MB' }}</span>
                  </div>

                  <div class="flex items-center gap-1.5">
                    <Calendar class="w-4 h-4 text-emerald-400" />
                    <span>تاريخ النشر:</span>
                    <span class="text-white font-bold">{{ latestVersionData?.published_at?.split(' ')[0] || 'اليوم' }}</span>
                  </div>
                </div>

                <!-- Changelog / Release Notes -->
                <div>
                  <div class="font-bold text-slate-300 mb-2 flex items-center gap-1.5">
                    <Sparkles class="w-4 h-4 text-amber-400" />
                    <span>أبرز المميزات والتحسينات الجديدة:</span>
                  </div>

                  <div class="p-3.5 rounded-2xl bg-slate-950/70 border border-slate-800/80 text-slate-300 space-y-1.5 max-h-36 overflow-y-auto custom-scrollbar leading-relaxed">
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
                      {{ latestVersionData?.release_notes_ar || 'تحسينات عامة في الأداء والسرعة واستقرار النظام.' }}
                    </p>
                  </div>
                </div>

                <!-- Download Progress Bar (When Downloading) -->
                <div v-if="isDownloading" class="space-y-2 pt-2">
                  <div class="flex items-center justify-between text-[11px] font-bold">
                    <span class="text-amber-400 animate-pulse">جاري تحميل حزمة الـ APK...</span>
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
              <div class="p-5 border-t border-slate-800 bg-slate-950/80 flex items-center gap-3">
                <button
                  type="button"
                  @click="startDownloadAndInstall"
                  :disabled="isDownloading"
                  class="flex-1 py-3 px-4 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-500/25 transition active:scale-95 cursor-pointer disabled:opacity-50"
                >
                  <Download v-if="!isDownloading" class="w-4.5 h-4.5" />
                  <div v-else class="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></div>
                  <span>{{ isDownloading ? `جاري التحميل (${downloadProgress}%)...` : 'تحديث وتثبيت الآن ⚡' }}</span>
                </button>

                <button
                  v-if="!isForceUpdate && !isDownloading"
                  type="button"
                  @click="closeModal"
                  class="py-3 px-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs transition active:scale-95 cursor-pointer"
                >
                  تذكيري لاحقاً
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
