<script setup>
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import LegalDocumentContent from '../../Components/Landing/LegalDocumentContent.vue';
import { useLegalDocument } from '../../Composables/useLegalDocument';

const route = useRoute();
const { blocks, version, isDraft, brand, isLoading, hasError, load } = useLegalDocument();

const documentKey = computed(() => String(route.meta?.legalDocument || 'terms'));

const refresh = () => load(documentKey.value);

watch(documentKey, refresh, { immediate: true });
</script>

<template>
  <LandingShell :brand="brand">
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6">
      <LegalDocumentContent
        :blocks="blocks"
        :version="version"
        :is-draft="isDraft"
        :is-loading="isLoading"
        :has-error="hasError"
        @retry="refresh"
      />
    </div>
  </LandingShell>
</template>
