<script setup>
import { computed, ref } from 'vue';
import { Calendar, Check, Copy, Download, HardDrive, Monitor, ShieldCheck, Smartphone } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';
import { bytesToMegabytes } from '../../helpers/appUpdate';

const props = defineProps({
  release: { type: Object, required: true },
  isCurrentPlatform: { type: Boolean, default: false },
});

const { formatDate } = useFormatters();

// Shell commands, not prose: shown verbatim so users can paste them.
const VERIFY_COMMANDS = {
  windows: 'certutil -hashfile <file> SHA256',
  android: 'sha256sum <file>',
};

const isWindows = computed(() => props.release.platform === 'windows');
const titleKey = computed(() =>
  isWindows.value ? 'app_update.download_page.windows_title' : 'app_update.download_page.android_title'
);
const installHintKey = computed(() =>
  isWindows.value ? 'app_update.download_page.windows_install_hint' : 'app_update.download_page.android_install_hint'
);
const sizeMb = computed(() => (props.release.sizeBytes > 0 ? bytesToMegabytes(props.release.sizeBytes) : null));
const publishedDate = computed(() => (props.release.publishedAt ? formatDate(props.release.publishedAt) : null));
const verifyCommand = computed(() => VERIFY_COMMANDS[props.release.platform] ?? VERIFY_COMMANDS.android);

const copied = ref(false);
const copyChecksum = async () => {
  try {
    await navigator.clipboard.writeText(props.release.checksum);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch {
    copied.value = false;
  }
};
</script>

<template>
  <article
    class="flex flex-col gap-4 rounded-3xl border bg-white p-5 shadow-sm dark:bg-slate-900 sm:p-6"
    :class="isCurrentPlatform ? 'border-[var(--color-primary-border)]' : 'border-slate-200 dark:border-slate-800'"
    :data-testid="`app-download-card-${release.platform}`"
  >
    <header class="flex items-start gap-3">
      <div
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
        style="background: var(--color-primary-light); color: var(--color-primary)"
      >
        <Monitor v-if="isWindows" class="h-6 w-6" />
        <Smartphone v-else class="h-6 w-6" />
      </div>
      <div class="min-w-0 flex-1 text-start">
        <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t(titleKey) }}</h2>
        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">
          {{ $t('app_update.new_version', { version: release.versionName ?? '' }) }}
        </p>
      </div>
      <span
        v-if="isCurrentPlatform"
        class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-black"
        style="background: var(--color-primary-light); color: var(--color-primary)"
      >
        {{ $t('app_update.download_page.your_device') }}
      </span>
    </header>

    <dl class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-500 dark:text-slate-400">
      <div v-if="sizeMb" class="flex items-center gap-1.5">
        <HardDrive class="h-4 w-4" />
        <dt>{{ $t('app_update.file_size') }}</dt>
        <dd class="font-bold text-slate-900 dark:text-white">
          {{ $t('app_update.download_page.size_mb', { size: sizeMb }) }}
        </dd>
      </div>
      <div v-if="publishedDate" class="flex items-center gap-1.5">
        <Calendar class="h-4 w-4" />
        <dt>{{ $t('app_update.publish_date') }}</dt>
        <dd class="font-bold text-slate-900 dark:text-white">{{ publishedDate }}</dd>
      </div>
    </dl>

    <a
      :href="release.downloadUrl"
      rel="noopener"
      class="flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-theme-gradient px-4 py-3 text-sm font-black text-white transition active:scale-95"
      :data-testid="`app-download-link-${release.platform}`"
    >
      <Download class="h-4 w-4" />
      <span>{{ $t('app_update.download_page.download_button') }}</span>
    </a>

    <section
      class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-950/60"
    >
      <div class="flex items-center gap-1.5 font-bold text-slate-700 dark:text-slate-300">
        <ShieldCheck class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
        <span>{{ $t('app_update.download_page.checksum_title') }}</span>
      </div>
      <div class="flex items-center gap-2">
        <code
          dir="ltr"
          class="min-w-0 flex-1 break-all rounded-xl bg-white px-2.5 py-2 font-mono text-[11px] text-slate-800 dark:bg-slate-900 dark:text-slate-200"
        >
          {{ release.checksum }}
        </code>
        <button
          type="button"
          class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-white text-slate-600 transition active:scale-90 dark:bg-slate-900 dark:text-slate-300"
          :aria-label="$t('app_update.download_page.copy_checksum')"
          @click="copyChecksum"
        >
          <Check v-if="copied" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
          <Copy v-else class="h-4 w-4" />
        </button>
      </div>
      <p class="leading-relaxed text-slate-500 dark:text-slate-400">
        {{ $t('app_update.download_page.checksum_hint') }}
      </p>
      <code dir="ltr" class="block font-mono text-[11px] text-slate-600 dark:text-slate-300">{{ verifyCommand }}</code>
    </section>

    <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $t(installHintKey) }}</p>
  </article>
</template>
