<script setup>
import { computed } from 'vue';
import { Lock } from 'lucide-vue-next';
import { useAppConfigStore } from '@/stores/appConfig';

const props = defineProps({
  feature: {
    type: String,
    default: null,
  },
  showFallback: {
    type: Boolean,
    default: false,
  },
});

const appConfigStore = useAppConfigStore();

// Safely evaluate feature access across tenant overrides and plan definitions
const isAllowed = computed(() => {
  if (!props.feature) {
    return true;
  }

  const tenant = appConfigStore.tenant;
  if (!tenant) {
    return true;
  }

  // 1. Check manual feature overrides on tenant
  if (Array.isArray(tenant.enabled_features) && tenant.enabled_features.includes(props.feature)) {
    return true;
  }

  // 2. Check plan features map
  if (tenant.plan && tenant.plan.features && typeof tenant.plan.features === 'object') {
    if (tenant.plan.features[props.feature] !== undefined) {
      return Boolean(tenant.plan.features[props.feature]);
    }
  }

  // 3. Fallback to direct features if present
  if (tenant.features && typeof tenant.features === 'object' && tenant.features[props.feature] !== undefined) {
    return Boolean(tenant.features[props.feature]);
  }

  return true;
});
</script>

<template>
  <template v-if="isAllowed">
    <slot />
  </template>
  <template v-else-if="showFallback">
    <slot name="fallback">
      <!-- No upgrade link: the plans screen belongs to the platform console (admin host); the
           tenant subscription / upgrade page arrives with ENTI-3.x. -->
      <div
        class="p-3.5 bg-theme-light border border-theme-border rounded-2xl flex items-center gap-2 text-xs text-theme-primary font-tajawal shadow-xs"
        role="note"
      >
        <Lock class="w-4 h-4 shrink-0" aria-hidden="true" />
        <span class="font-bold">{{ $t('super.plan_upgrade_required') }}</span>
      </div>
    </slot>
  </template>
</template>
