<template>
  <div class="space-y-6 font-tajawal">
    <!-- Read-Only Notice -->
    <div
      v-if="!canManage"
      class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-center gap-3 text-xs font-bold"
    >
      <ShieldAlert class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
      <span>{{ $t('settings.read_only_notice') }}</span>
    </div>

    <!-- Main Branding Card -->
    <div
      class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-7 shadow-xs dark:shadow-xl space-y-6 transition-colors"
    >
      <!-- Section Header -->
      <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center shrink-0"
          >
            <Palette class="w-5 h-5" />
          </div>
          <div>
            <h2 class="text-base font-black text-slate-900 dark:text-white">
              {{ $t('settings.tab_branding') }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              {{ $t('settings.tab_branding_sub') }}
            </p>
          </div>
        </div>
      </div>

      <!-- 1. Dual Logo Upload Section (Light & Dark) -->
      <div class="space-y-4">
        <h3 class="text-xs font-black text-slate-700 dark:text-slate-300 flex items-center gap-2">
          <Building2 class="w-4 h-4 text-theme-primary" />
          <span>{{ $t('settings.company_logo') }}</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- ☀️ Light Mode Logo Card -->
          <div
            id="setting-logo_light"
            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <Sun class="w-4 h-4 text-amber-500 shrink-0" />
                <h4 class="text-xs font-black text-slate-900 dark:text-white truncate">
                  {{ $t('settings.company_logo_light') }}
                </h4>
              </div>

              <!-- Delete Button -->
              <button
                v-if="canManage && logoLightUrl"
                type="button"
                @click="$emit('remove-logo', 'light')"
                :disabled="isUploadingLight"
                class="min-h-[36px] px-2.5 py-1 rounded-xl text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold flex items-center gap-1 transition cursor-pointer disabled:opacity-50"
              >
                <Trash2 class="w-3.5 h-3.5" />
                <span>{{ $t('settings.remove_logo') }}</span>
              </button>
            </div>

            <div class="flex items-center gap-4">
              <!-- Preview Box -->
              <div
                class="w-20 h-20 rounded-2xl bg-white border border-slate-200 dark:border-slate-700 p-2 flex items-center justify-center overflow-hidden shadow-xs shrink-0"
              >
                <img
                  v-if="logoLightUrl"
                  :src="logoLightUrl"
                  :alt="$t('settings.company_logo_light')"
                  data-testid="logo-light-preview"
                  class="w-full h-full object-contain"
                />
                <div v-else class="text-slate-300 dark:text-slate-600 flex items-center justify-center">
                  <Sun class="w-8 h-8 opacity-40" />
                </div>
              </div>

              <div class="space-y-2 flex-1 min-w-0">
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">
                  {{ $t('settings.logo_light_hint') }}
                </p>

                <label
                  v-if="canManage"
                  class="inline-flex items-center gap-1.5 min-h-[44px] px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-700 cursor-pointer transition shadow-xs select-none"
                  :class="{ 'opacity-50 pointer-events-none': isUploadingLight }"
                >
                  <Upload class="w-3.5 h-3.5" />
                  <span>{{ isUploadingLight ? $t('common.loading') : $t('settings.choose_logo_light') }}</span>
                  <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/*"
                    data-testid="logo-light-input"
                    @change="handleFileSelect('light', $event)"
                    class="hidden"
                  />
                </label>
              </div>
            </div>

            <!-- Light Logo 422 Validation Error -->
            <p
              v-if="logoLightError"
              data-testid="logo-light-error"
              class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1.5 animate-in fade-in pt-1"
            >
              <AlertCircle class="w-4 h-4 shrink-0" />
              <span>{{ logoLightError }}</span>
            </p>
          </div>

          <!-- 🌙 Dark Mode Logo Card -->
          <div
            id="setting-logo_dark"
            class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <Moon class="w-4 h-4 text-indigo-400 shrink-0" />
                <h4 class="text-xs font-black text-slate-900 dark:text-white truncate">
                  {{ $t('settings.company_logo_dark') }}
                </h4>
              </div>

              <!-- Delete Button -->
              <button
                v-if="canManage && logoDarkUrl"
                type="button"
                @click="$emit('remove-logo', 'dark')"
                :disabled="isUploadingDark"
                class="min-h-[36px] px-2.5 py-1 rounded-xl text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold flex items-center gap-1 transition cursor-pointer disabled:opacity-50"
              >
                <Trash2 class="w-3.5 h-3.5" />
                <span>{{ $t('settings.remove_logo') }}</span>
              </button>
            </div>

            <div class="flex items-center gap-4">
              <!-- Preview Box -->
              <div
                class="w-20 h-20 rounded-2xl bg-slate-900 border border-slate-700 p-2 flex items-center justify-center overflow-hidden shadow-xs shrink-0"
              >
                <img
                  v-if="logoDarkUrl"
                  :src="logoDarkUrl"
                  :alt="$t('settings.company_logo_dark')"
                  data-testid="logo-dark-preview"
                  class="w-full h-full object-contain"
                />
                <div v-else class="text-slate-600 flex items-center justify-center">
                  <Moon class="w-8 h-8 opacity-40" />
                </div>
              </div>

              <div class="space-y-2 flex-1 min-w-0">
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">
                  {{ $t('settings.logo_dark_hint') }}
                </p>

                <label
                  v-if="canManage"
                  class="inline-flex items-center gap-1.5 min-h-[44px] px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-700 cursor-pointer transition shadow-xs select-none"
                  :class="{ 'opacity-50 pointer-events-none': isUploadingDark }"
                >
                  <Upload class="w-3.5 h-3.5" />
                  <span>{{ isUploadingDark ? $t('common.loading') : $t('settings.choose_logo_dark') }}</span>
                  <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/*"
                    data-testid="logo-dark-input"
                    @change="handleFileSelect('dark', $event)"
                    class="hidden"
                  />
                </label>
              </div>
            </div>

            <!-- Dark Logo 422 Validation Error -->
            <p
              v-if="logoDarkError"
              data-testid="logo-dark-error"
              class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1.5 animate-in fade-in pt-1"
            >
              <AlertCircle class="w-4 h-4 shrink-0" />
              <span>{{ logoDarkError }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- 2. System Theme Color Section -->
      <div id="setting-system_theme_color" class="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between">
          <label class="block text-xs font-black text-slate-700 dark:text-slate-300">
            {{ $t('branding.attributes.system_theme_color') }}
          </label>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5">
          <button
            v-for="color in colorPalettes"
            :key="color.id"
            type="button"
            @click="canManage && handleSelectColor(color.id)"
            class="min-h-[44px] p-2.5 rounded-2xl border text-xs font-bold flex items-center gap-2 transition-all cursor-pointer select-none"
            :class="
              form.system_theme_color === color.id
                ? 'border-theme-primary ring-2 ring-theme-primary/30 bg-theme-light text-slate-900 dark:text-white'
                : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:border-slate-300'
            "
          >
            <span class="w-4 h-4 rounded-full shrink-0 shadow-xs" :style="{ backgroundColor: color.hex }"></span>
            <span class="truncate text-[11px]">{{ color.name }}</span>
          </button>
        </div>

        <!-- Custom Hex Input -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 pt-1">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('settings.custom_hex') }}:</span>
            <input
              type="text"
              :value="form.system_theme_color"
              @input="canManage && $emit('update:field', 'system_theme_color', $event.target.value)"
              :disabled="!canManage"
              placeholder="#10B981"
              maxlength="7"
              class="w-28 min-h-[38px] px-3 py-1 text-xs font-mono font-bold rounded-xl border bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border-slate-300 dark:border-slate-700 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 outline-hidden"
            />
          </div>
        </div>

        <p
          v-if="themeColorError"
          class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1.5 pt-1"
        >
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ themeColorError }}</span>
        </p>
      </div>

      <!-- 3. Receipt Header Lines -->
      <div id="setting-receipt_header_lines" class="space-y-2 pt-3 border-t border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between">
          <label for="receipt_header_input" class="block text-xs font-black text-slate-700 dark:text-slate-300">
            {{ $t('branding.attributes.receipt_header_lines') }}
          </label>
        </div>

        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
          {{ $t('settings.receipt_header_hint') }}
        </p>

        <textarea
          id="receipt_header_input"
          :value="form.receipt_header_lines"
          @input="canManage && $emit('update:field', 'receipt_header_lines', $event.target.value)"
          :disabled="!canManage"
          rows="4"
          :placeholder="$t('settings.receipt_header_placeholder')"
          class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 rounded-2xl px-4 py-3 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-hidden transition leading-relaxed disabled:opacity-50 disabled:cursor-not-allowed font-mono"
        ></textarea>

        <p
          v-if="receiptHeaderError"
          class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1.5 pt-1"
        >
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ receiptHeaderError }}</span>
        </p>
      </div>

      <!-- 4. Receipt Footer Text -->
      <div id="setting-receipt_footer_text" class="space-y-2 pt-3 border-t border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between">
          <label for="receipt_footer_input" class="block text-xs font-black text-slate-700 dark:text-slate-300">
            {{ $t('branding.attributes.receipt_footer_text') }}
          </label>
        </div>

        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
          {{ $t('settings.receipt_footer_hint') }}
        </p>

        <textarea
          id="receipt_footer_input"
          :value="form.receipt_footer_text"
          @input="canManage && $emit('update:field', 'receipt_footer_text', $event.target.value)"
          :disabled="!canManage"
          rows="3"
          :placeholder="$t('settings.receipt_footer_placeholder')"
          class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 rounded-2xl px-4 py-3 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-hidden transition leading-relaxed disabled:opacity-50 disabled:cursor-not-allowed font-mono"
        ></textarea>

        <p
          v-if="receiptFooterError"
          class="text-xs font-bold text-rose-500 dark:text-rose-400 flex items-center gap-1.5 pt-1"
        >
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ receiptFooterError }}</span>
        </p>
      </div>
    </div>

    <!-- Section Save Actions Footer -->
    <div
      v-if="canManage"
      class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs"
    >
      <div class="flex items-center gap-2">
        <span
          v-if="isSectionDirty"
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 animate-pulse"
        >
          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          <span>{{ $t('settings.unsaved_changes') }}</span>
        </span>
      </div>

      <div class="w-full sm:w-auto flex items-center justify-end gap-3">
        <BaseButton
          type="button"
          variant="primary"
          size="md"
          :loading="isSaving"
          @click="$emit('save', 'branding')"
          class="w-full sm:w-auto font-black shadow-theme-primary shadow-lg flex items-center justify-center gap-2 min-h-[44px]"
        >
          <Save class="w-4 h-4" />
          <span>{{ $t('settings.save_section') }}</span>
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Palette, Building2, Sun, Moon, Upload, Trash2, AlertCircle, Save, ShieldAlert } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  form: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  canManage: { type: Boolean, default: true },
  isSaving: { type: Boolean, default: false },
  initialForm: { type: Object, default: null },
  colorPalettes: { type: Array, default: () => [] },
  logoLightUrl: { type: String, default: null },
  logoDarkUrl: { type: String, default: null },
  logoLightError: { type: String, default: null },
  logoDarkError: { type: String, default: null },
  isUploadingLight: { type: Boolean, default: false },
  isUploadingDark: { type: Boolean, default: false },
});

const emit = defineEmits(['update:field', 'save', 'upload-logo', 'remove-logo', 'select-color']);

const brandingFields = ['receipt_header_lines', 'receipt_footer_text', 'system_theme_color'];

const isSectionDirty = computed(() => {
  if (!props.initialForm) return false;
  return brandingFields.some((f) => props.form[f] !== props.initialForm[f]);
});

const themeColorError = computed(() => {
  const err = props.errors?.system_theme_color;
  return Array.isArray(err) ? err[0] : err;
});

const receiptHeaderError = computed(() => {
  const err = props.errors?.receipt_header_lines;
  return Array.isArray(err) ? err[0] : err;
});

const receiptFooterError = computed(() => {
  const err = props.errors?.receipt_footer_text;
  return Array.isArray(err) ? err[0] : err;
});

const handleFileSelect = (variant, event) => {
  const file = event.target.files?.[0];
  if (file) {
    emit('upload-logo', variant, file);
  }
  event.target.value = '';
};

const handleSelectColor = (colorId) => {
  emit('select-color', colorId);
  emit('update:field', 'system_theme_color', colorId);
};
</script>
