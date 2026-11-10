<script setup>
import { onMounted } from 'vue';
import { FileUp } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import ImportStepper from '../../Components/Imports/ImportStepper.vue';
import ImportTypeStep from '../../Components/Imports/ImportTypeStep.vue';
import ImportUploadStep from '../../Components/Imports/ImportUploadStep.vue';
import ImportPreviewStep from '../../Components/Imports/ImportPreviewStep.vue';
import ImportResultStep from '../../Components/Imports/ImportResultStep.vue';
import { useImport } from '../../Composables/useImport';

const imp = useImport();
const { step, type, filter, skipInvalid } = imp;

onMounted(imp.initFromRoute);
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <PageHeader :title="$t('import.ui.title')" :subtitle="$t('import.ui.subtitle')" :icon="FileUp" />
    <ImportStepper :current="step" />

    <div class="p-4 sm:p-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60">
      <ImportTypeStep
        v-if="step === 'type'"
        :allowed-types="imp.allowedTypes.value"
        :selected="type"
        :store="imp.activeStore.value"
        @select="imp.selectType"
        @continue="imp.goToUpload"
      />
      <ImportUploadStep
        v-else-if="step === 'upload'"
        :type="type"
        :store="imp.activeStore.value"
        :notice="imp.notice.value"
        :is-uploading="imp.isPreviewing.value"
        :is-downloading="imp.isDownloading.value"
        :file-name="imp.file.value?.name || ''"
        @file="imp.uploadFile"
        @download="imp.downloadTemplate"
        @back="imp.backToType"
      />
      <ImportPreviewStep
        v-else-if="step === 'preview'"
        v-model:filter="filter"
        v-model:skip-invalid="skipInvalid"
        :type="type"
        :preview="imp.preview.value"
        :summary="imp.summary.value"
        :rows="imp.visibleRows.value"
        :needs-skip-confirmation="imp.needsSkipConfirmation.value"
        :can-commit="imp.canCommit.value"
        :importable-count="imp.importableCount.value"
        :is-committing="imp.isCommitting.value"
        @commit="imp.commit"
        @back="imp.backToUpload"
      />
      <ImportResultStep v-else :result="imp.result.value" :list-path="imp.listPath.value" @restart="imp.restart" />
    </div>
  </div>
</template>
