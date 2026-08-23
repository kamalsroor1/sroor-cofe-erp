<template>
  <div class="space-y-6 max-w-3xl mx-auto font-tajawal">
    <!-- Page Header -->
    <PageHeader
      :title="$t('profile.title')"
      :subtitle="$t('profile.subtitle')"
      icon="👤"
    />

    <!-- Loading Skeleton State -->
    <div v-if="isLoading" class="p-16 text-center">
      <div class="w-10 h-10 border-4 border-theme-primary border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-xs text-slate-400">{{ $t('profile.profile_loading') }}</p>
    </div>

    <!-- Main Profile Form -->
    <form v-else @submit.prevent="submitProfile" class="space-y-6">
      <!-- Personal Information Card -->
      <ProfileBasicInfoCard
        :form="form"
        @update:field="updateField"
      />

      <!-- Security / Password Card -->
      <ProfileSecurityCard
        :form="form"
        @update:field="updateField"
      />

      <!-- Appearance / Theme Preferences Card -->
      <ProfileThemeCard
        :form="form"
        @update:field="updateField"
      />

      <!-- Submit Action Button -->
      <div class="flex justify-end">
        <BaseButton
          type="submit"
          variant="primary"
          size="md"
          :loading="isSubmitting"
          class="shadow-lg shadow-theme-primary font-black"
        >
          {{ isSubmitting ? $t('profile.saving_profile') : $t('profile.save_changes') }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import ProfileBasicInfoCard from '../../Components/Profile/ProfileBasicInfoCard.vue';
import ProfileSecurityCard from '../../Components/Profile/ProfileSecurityCard.vue';
import ProfileThemeCard from '../../Components/Profile/ProfileThemeCard.vue';
import { useProfile } from '../../Composables/useProfile';

const {
  isLoading,
  isSubmitting,
  form,
  updateField,
  submitProfile,
} = useProfile();
</script>
