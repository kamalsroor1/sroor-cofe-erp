<script setup>
import { computed } from 'vue';
import { Images } from 'lucide-vue-next';
import DashboardSectionCard from '../../Common/DashboardSectionCard.vue';
import PlatformAssetUploader from './PlatformAssetUploader.vue';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  assets: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  busySlot: { type: String, default: null },
  readonly: { type: Boolean, default: false },
});

defineEmits(['upload', 'remove']);

const { t } = useTrans();

// UI hint only: the server sniffs magic bytes and refuses SVG/GIF whatever the picker allows.
const LOGO_ACCEPT = 'image/png,image/jpeg,image/webp';
const ICON_ACCEPT = 'image/png';

const slots = computed(() => [
  {
    key: 'logo_light',
    title: t('super.platform_settings.assets.logo_light'),
    hint: t('super.platform_settings.assets.logo_hint'),
    accept: LOGO_ACCEPT,
    dark: false,
  },
  {
    key: 'logo_dark',
    title: t('super.platform_settings.assets.logo_dark'),
    hint: t('super.platform_settings.assets.logo_hint'),
    accept: LOGO_ACCEPT,
    dark: true,
  },
  {
    key: 'favicon',
    title: t('super.platform_settings.assets.favicon'),
    hint: t('super.platform_settings.assets.favicon_hint'),
    accept: ICON_ACCEPT,
    dark: false,
  },
  {
    key: 'app_icon',
    title: t('super.platform_settings.assets.app_icon'),
    hint: t('super.platform_settings.assets.app_icon_hint'),
    accept: ICON_ACCEPT,
    dark: false,
  },
]);

const isLocked = (key) => props.busySlot !== null && props.busySlot !== key;
</script>

<template>
  <DashboardSectionCard
    :title="$t('super.platform_settings.sections.assets_title')"
    :subtitle="$t('super.platform_settings.sections.assets_subtitle')"
    :icon="Images"
    icon-bg="bg-amber-100 dark:bg-amber-500/15"
    icon-color="text-amber-600 dark:text-amber-400"
    wrapper-class="shadow-sm dark:shadow-xl"
  >
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <PlatformAssetUploader
        v-for="item in slots"
        :key="item.key"
        :title="item.title"
        :hint="item.hint"
        :accept="item.accept"
        :dark-preview="item.dark"
        :asset="assets[item.key]"
        :busy="busySlot === item.key"
        :locked="isLocked(item.key)"
        :readonly="readonly"
        :error="errors[item.key] || ''"
        :test-id="`platform-asset-${item.key}`"
        @upload="$emit('upload', item.key, $event)"
        @remove="$emit('remove', item.key)"
      />
    </div>
  </DashboardSectionCard>
</template>
