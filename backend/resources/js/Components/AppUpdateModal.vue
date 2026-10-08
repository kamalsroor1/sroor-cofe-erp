<script setup>
import { computed } from 'vue';
import {
  AlertTriangle,
  Calendar,
  CheckCircle,
  Download,
  HardDrive,
  Loader2,
  RefreshCw,
  Rocket,
  ShieldAlert,
  Sparkles,
  X,
} from 'lucide-vue-next';
import { useAppUpdate } from '../Composables/useAppUpdate';
import { bytesToMegabytes } from '../helpers/appUpdate';

const {
  isEligible,
  currentVersionName,
  isForceUpdate,
  update,
  isModalOpen,
  isDownloading,
  isDownloaded,
  downloadProgress,
  isProgressIndeterminate,
  downloadedBytes,
  totalBytes,
  stage,
  errorKey,
  downloadStageText,
  startDownloadAndInstall,
  closeModal,
} = useAppUpdate();

const canDismiss = computed(() => !isForceUpdate.value && !isDownloading.value);
const releaseNotes = computed(() => update.value?.releaseNotes ?? []);
const publishedDate = computed(() => update.value?.publishedAt?.split(' ')[0] ?? null);
const downloadedMb = computed(() => bytesToMegabytes(downloadedBytes.value));
const totalMb = computed(() => bytesToMegabytes(totalBytes.value));
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="isEligible && isModalOpen && update"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm select-none font-tajawal"
        role="dialog"
        aria-modal="true"
        @click.self="canDismiss && closeModal()"
        @keydown.esc="canDismiss && closeModal()"
      >
        <div
          class="relative w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white text-slate-900 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
        >
          <!-- Installer opened -->
          <div v-if="isDownloaded" class="space-y-4 p-6 text-center">
            <div
              class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
            >
              <CheckCircle class="h-8 w-8" />
            </div>
            <div class="space-y-1.5">
              <h2 class="text-lg font-black">{{ $t('app_update.installer_opened_title') }}</h2>
              <p class="px-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                {{ $t('app_update.installer_opened_desc') }}
              </p>
            </div>
            <div class="flex items-center gap-3 pt-2">
              <button
                type="button"
                class="flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-theme-gradient px-4 py-3 text-sm font-black text-white transition active:scale-95"
                @click="startDownloadAndInstall"
              >
                <RefreshCw class="h-4 w-4" />
                <span>{{ $t('app_update.open_installer_again') }}</span>
              </button>
              <button
                v-if="!isForceUpdate"
                type="button"
                class="min-h-11 cursor-pointer rounded-2xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition active:scale-95 dark:bg-slate-800 dark:text-slate-200"
                @click="closeModal"
              >
                {{ $t('common.close') }}
              </button>
            </div>
          </div>

          <template v-else>
            <!-- Header -->
            <div class="relative border-b border-slate-200 p-6 text-center dark:border-slate-800">
              <button
                v-if="canDismiss"
                type="button"
                class="absolute start-4 top-4 flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition active:scale-90 dark:bg-slate-800 dark:text-slate-400"
                :aria-label="$t('common.close')"
                @click="closeModal"
              >
                <X class="h-5 w-5" />
              </button>

              <div
                class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-3xl"
                style="background: var(--color-primary-light); color: var(--color-primary)"
              >
                <Rocket class="h-8 w-8" />
              </div>

              <h2 class="text-lg font-black">
                {{ isForceUpdate ? $t('app_update.mandatory_update_title') : $t('app_update.update_available_title') }}
              </h2>

              <div
                class="mt-2 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold dark:border-slate-700 dark:bg-slate-800"
              >
                <span class="text-slate-500 dark:text-slate-400">
                  {{ $t('app_update.current_version') }} v{{ currentVersionName }}
                </span>
                <span class="text-emerald-600 dark:text-emerald-400">
                  {{ $t('app_update.new_version', { version: update.versionName ?? '' }) }}
                </span>
              </div>
            </div>

            <!-- Body -->
            <div class="space-y-4 p-6 text-xs">
              <div
                v-if="isForceUpdate"
                class="flex items-start gap-2.5 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-3 text-rose-700 dark:text-rose-300"
              >
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                  <div class="font-bold">{{ $t('app_update.security_update_badge') }}</div>
                  <p class="mt-0.5 leading-relaxed">{{ $t('app_update.mandatory_update_desc') }}</p>
                </div>
              </div>

              <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
              >
                <div v-if="update.fileSize" class="flex items-center gap-1.5">
                  <HardDrive class="h-4 w-4" />
                  <span>{{ $t('app_update.file_size') }}</span>
                  <span class="font-bold text-slate-900 dark:text-white">{{ update.fileSize }}</span>
                </div>
                <div v-if="publishedDate" class="flex items-center gap-1.5">
                  <Calendar class="h-4 w-4" />
                  <span>{{ $t('app_update.publish_date') }}</span>
                  <span class="font-bold text-slate-900 dark:text-white">{{ publishedDate }}</span>
                </div>
              </div>

              <div>
                <div class="mb-2 flex items-center gap-1.5 font-bold text-slate-700 dark:text-slate-300">
                  <Sparkles class="h-4 w-4" />
                  <span>{{ $t('app_update.changelog_title') }}</span>
                </div>
                <div
                  class="max-h-36 space-y-1.5 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-3.5 leading-relaxed text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-300"
                >
                  <template v-if="releaseNotes.length">
                    <div v-for="(note, idx) in releaseNotes" :key="idx" class="flex items-start gap-2">
                      <span class="shrink-0">•</span>
                      <span>{{ note }}</span>
                    </div>
                  </template>
                  <p v-else class="text-slate-500 dark:text-slate-400">{{ $t('app_update.general_improvements') }}</p>
                </div>
              </div>

              <!-- Real progress -->
              <div v-if="isDownloading" class="space-y-2 pt-1" aria-live="polite">
                <div class="flex items-center justify-between gap-2 font-bold">
                  <span class="flex items-center gap-1.5" style="color: var(--color-primary)">
                    <Loader2 class="h-3.5 w-3.5 shrink-0 animate-spin" />
                    <span>{{ downloadStageText }}</span>
                  </span>
                  <span v-if="!isProgressIndeterminate" class="font-mono text-slate-900 dark:text-white">
                    {{ downloadProgress }}%
                  </span>
                </div>
                <div
                  class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                  role="progressbar"
                  :aria-valuenow="isProgressIndeterminate ? undefined : downloadProgress"
                  aria-valuemin="0"
                  aria-valuemax="100"
                >
                  <div
                    class="h-full rounded-full transition-all duration-150"
                    :class="{ 'w-1/3 animate-pulse': isProgressIndeterminate }"
                    style="background: var(--color-primary)"
                    :style="isProgressIndeterminate ? {} : { width: `${downloadProgress}%` }"
                  ></div>
                </div>
                <p v-if="totalBytes > 0" class="text-slate-500 dark:text-slate-400">
                  {{ $t('app_update.downloaded_of', { done: downloadedMb, total: totalMb }) }}
                </p>
              </div>

              <!-- Error (checksum / signature / permission / network) -->
              <div
                v-if="stage === 'error' && errorKey"
                class="flex items-start gap-2.5 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-3 text-rose-700 dark:text-rose-300"
                role="alert"
              >
                <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0" />
                <p class="leading-relaxed">{{ $t(errorKey) }}</p>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center gap-3 border-t border-slate-200 p-5 dark:border-slate-800">
              <button
                type="button"
                :disabled="isDownloading"
                class="flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-2xl bg-theme-gradient px-4 py-3 text-sm font-black text-white transition active:scale-95 disabled:opacity-50"
                @click="startDownloadAndInstall"
              >
                <Loader2 v-if="isDownloading" class="h-4 w-4 animate-spin" />
                <RefreshCw v-else-if="stage === 'error'" class="h-4 w-4" />
                <Download v-else class="h-4 w-4" />
                <span>
                  {{
                    isDownloading
                      ? $t('app_update.downloading_package')
                      : stage === 'error'
                        ? $t('app_update.retry_update')
                        : $t('app_update.update_and_install_now')
                  }}
                </span>
              </button>
              <button
                v-if="canDismiss"
                type="button"
                class="min-h-11 cursor-pointer rounded-2xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition active:scale-95 dark:bg-slate-800 dark:text-slate-200"
                @click="closeModal"
              >
                {{ $t('app_update.remind_later') }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
