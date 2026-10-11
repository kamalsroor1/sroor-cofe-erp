<script setup>
import { ref } from 'vue';
import { AlertCircle, ImageOff, Trash2, Upload } from 'lucide-vue-next';

defineProps({
  title: { type: String, required: true },
  hint: { type: String, default: '' },
  asset: { type: Object, default: () => ({ url: null, custom: false }) },
  accept: { type: String, default: 'image/png' },
  darkPreview: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  error: { type: String, default: '' },
  testId: { type: String, default: '' },
});

const emit = defineEmits(['upload', 'remove']);

const fileInput = ref(null);

const onFileChange = (event) => {
  const file = event.target.files?.[0];
  if (file) emit('upload', file);
  // Lets the operator pick the same file again after a 422.
  event.target.value = '';
};
</script>

<template>
  <div
    class="bg-white dark:bg-slate-900/90 border rounded-2xl p-4 flex flex-col gap-3 shadow-sm"
    :class="error ? 'border-rose-400 dark:border-rose-500/70' : 'border-slate-200 dark:border-slate-800'"
    :data-testid="testId || undefined"
  >
    <div class="flex items-center justify-between gap-2">
      <h3 class="text-xs font-black text-slate-900 dark:text-white truncate">{{ title }}</h3>
      <span
        v-if="asset.url"
        class="px-2 py-0.5 rounded-full text-[10px] font-black shrink-0 border"
        :class="
          asset.custom
            ? 'bg-theme-light text-theme-primary border-theme-primary/30'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'
        "
      >
        {{
          asset.custom
            ? $t('super.platform_settings.assets.custom_badge')
            : $t('super.platform_settings.assets.default_badge')
        }}
      </span>
    </div>

    <div
      class="h-28 rounded-2xl border flex items-center justify-center p-3 overflow-hidden"
      :class="darkPreview ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-200 dark:border-slate-700'"
    >
      <img v-if="asset.url" :src="asset.url" :alt="title" class="max-w-full max-h-full object-contain" />
      <div v-else class="flex flex-col items-center gap-1 text-slate-400 dark:text-slate-500">
        <ImageOff class="w-7 h-7 opacity-60" />
        <span class="text-[11px] font-bold">{{ $t('super.platform_settings.assets.no_image') }}</span>
      </div>
    </div>

    <div class="space-y-1">
      <p v-if="hint" class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">{{ hint }}</p>
      <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
        {{ $t('super.platform_settings.assets.refused_formats') }}
      </p>
    </div>

    <p v-if="error" role="alert" class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-start gap-1.5">
      <AlertCircle class="w-4 h-4 shrink-0 mt-px" />
      <span>{{ error }}</span>
    </p>

    <div v-if="!readonly" class="mt-auto flex flex-wrap gap-2">
      <input ref="fileInput" type="file" class="sr-only" tabindex="-1" :accept="accept" @change="onFileChange" />
      <button
        type="button"
        class="flex-1 min-h-11 px-3 rounded-xl text-xs font-bold inline-flex items-center justify-center gap-1.5 border transition cursor-pointer active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-700"
        :disabled="busy || locked"
        :aria-busy="busy"
        @click="fileInput?.click()"
      >
        <span
          v-if="busy"
          class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin shrink-0"
        ></span>
        <Upload v-else class="w-4 h-4 shrink-0" />
        <span v-if="busy">{{ $t('super.platform_settings.assets.uploading') }}</span>
        <span v-else-if="asset.custom">{{ $t('super.platform_settings.assets.replace') }}</span>
        <span v-else>{{ $t('super.platform_settings.assets.upload') }}</span>
      </button>
      <button
        v-if="asset.custom"
        type="button"
        class="min-h-11 min-w-11 px-3 rounded-xl text-xs font-bold inline-flex items-center justify-center border transition cursor-pointer active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-900/60 hover:bg-rose-50 dark:hover:bg-rose-950/40"
        :disabled="busy || locked"
        :aria-label="$t('super.platform_settings.assets.remove')"
        :title="$t('super.platform_settings.assets.remove')"
        @click="emit('remove')"
      >
        <Trash2 class="w-4 h-4 shrink-0" />
      </button>
    </div>
  </div>
</template>
