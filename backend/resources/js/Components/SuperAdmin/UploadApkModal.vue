<template>
  <AppModal :show="show" :title="$t('super.upload_apk_modal_title')" @close="$emit('close')">
    <form @submit.prevent="$emit('submit')" class="space-y-4 text-xs font-tajawal">
      <div
        v-if="generalError"
        role="alert"
        class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-3 font-bold text-rose-700 dark:text-rose-300"
      >
        {{ generalError }}
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <BaseInput
            :model-value="form.version_name"
            @update:model-value="$emit('update:field', 'version_name', $event)"
            :label="$t('super.readable_version_name')"
            :error="fieldError('version_name')"
            placeholder="1.1.0"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.version_code"
            @update:model-value="$emit('update:field', 'version_code', Number($event))"
            :label="$t('super.version_code_label')"
            :error="fieldError('version_code')"
            :hint="versionCodeHint"
            type="number"
            min="1"
            placeholder="2"
            class="font-mono"
            required
          />
        </div>

        <div>
          <BaseSelect
            :model-value="form.platform"
            @update:model-value="$emit('update:field', 'platform', $event)"
            :options="platformOptions"
            :label="$t('super.target_platform_label')"
            :error="fieldError('platform')"
            :searchable="false"
            required
          />
        </div>

        <div>
          <BaseInput
            :model-value="form.min_version_code"
            @update:model-value="$emit('update:field', 'min_version_code', Number($event))"
            :label="$t('super.min_supported_version')"
            :error="fieldError('min_version_code')"
            type="number"
            min="1"
            placeholder="1"
            class="font-mono"
          />
        </div>
      </div>

      <!-- Force update toggle -->
      <BaseCheckbox
        :model-value="form.is_force_update"
        @update:model-value="$emit('update:field', 'is_force_update', $event)"
        :label="$t('super.force_update_label')"
        :description="$t('super.force_update_desc')"
        wrapper-class="p-3.5 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800"
      />

      <!-- Release notes textarea -->
      <BaseTextarea
        :model-value="form.release_notes_ar"
        @update:model-value="$emit('update:field', 'release_notes_ar', $event)"
        :label="$t('super.release_notes_ar_label')"
        :error="fieldError('release_notes_ar')"
        :rows="3"
        :placeholder="$t('app_update.upload.release_notes_placeholder')"
        required
      />

      <!-- File Upload -->
      <div class="space-y-1.5">
        <label :for="fileInputId" class="block font-bold text-slate-700 dark:text-slate-300">
          {{ $t(isWindows ? 'app_update.upload.file_label_windows' : 'app_update.upload.file_label_android') }}
        </label>
        <input
          :id="fileInputId"
          type="file"
          :accept="fileAcceptTypes"
          :aria-invalid="!!fieldError('apk_file')"
          @change="$emit('file-change', $event)"
          class="w-full min-h-11 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs file:me-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-purple-600 file:text-white cursor-pointer"
        />
        <span v-if="fieldError('apk_file')" class="block text-[11px] font-bold text-rose-600 dark:text-rose-400">
          {{ fieldError('apk_file') }}
        </span>
        <span v-else class="text-[10px] text-slate-500 dark:text-slate-400 block">
          {{ $t(isWindows ? 'app_update.upload.file_hint_windows' : 'app_update.upload.file_hint_android') }}
        </span>
      </div>

      <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="secondary" size="md" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </BaseButton>

        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="isSubmitting"
          class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold shadow-lg shadow-purple-500/25"
        >
          <Rocket class="w-4 h-4" />
          <span>{{ isSubmitting ? $t('super.publishing_status') : $t('super.publish_now_btn') }}</span>
        </BaseButton>
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { computed, useId } from 'vue';
import { Rocket } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseCheckbox from '../Form/BaseCheckbox.vue';
import BaseTextarea from '../Form/BaseTextarea.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';
import { semverCode } from '../../helpers/appUpdate';

const props = defineProps({
  show: { type: Boolean, default: false },
  form: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
  // Laravel 422 `errors` ({ field: [messages] }) of POST /super-admin/app-versions.
  errors: { type: Object, default: () => ({}) },
  // A refusal not tied to one field (e.g. the server's message for a 409/500).
  generalError: { type: String, default: '' },
});

defineEmits(['close', 'submit', 'update:field', 'file-change']);

const { t } = useTrans();
const fileInputId = useId();

// iOS is not a release platform (CTO Q7): the server only accepts Android APKs and Windows installers.
const platformOptions = computed(() => [
  { value: 'android', label: t('app_update.upload.platform_android') },
  { value: 'windows', label: t('app_update.upload.platform_windows') },
]);

const isWindows = computed(() => props.form?.platform === 'windows');
const fileAcceptTypes = computed(() => (isWindows.value ? '.exe' : '.apk'));

// Windows builds only know their semantic version, so their code must be SemverCode(version_name).
const versionCodeHint = computed(() => {
  if (!isWindows.value) return '';
  const code = semverCode(String(props.form?.version_name ?? ''));
  return code > 0 ? t('app_update.upload.windows_code_hint', { code }) : t('app_update.upload.windows_code_rule');
});

const fieldError = (field) => {
  const value = props.errors?.[field];
  if (Array.isArray(value)) return value[0] || null;
  return typeof value === 'string' && value !== '' ? value : null;
};
</script>
