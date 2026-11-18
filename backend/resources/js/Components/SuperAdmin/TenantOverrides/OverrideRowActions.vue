<script setup>
import { Pencil, Trash2 } from 'lucide-vue-next';
import BaseButton from '../../Common/BaseButton.vue';

/** Action buttons of one override row. Slot `extra` carries feature-only actions (grant / revoke). */
defineProps({
  canManage: { type: Boolean, default: false },
  hasOverride: { type: Boolean, default: false },
  showEdit: { type: Boolean, default: false },
  isRemoving: { type: Boolean, default: false },
});

defineEmits(['edit', 'remove']);
</script>

<template>
  <div v-if="canManage" class="flex flex-wrap items-center gap-2 md:justify-end">
    <slot name="extra" />
    <BaseButton
      v-if="showEdit"
      variant="outline"
      size="sm"
      class="min-h-[44px]"
      :icon="Pencil"
      :label="$t('plan_overrides.ui.edit')"
      @click="$emit('edit')"
    />
    <BaseButton
      v-if="hasOverride"
      variant="danger"
      size="sm"
      class="min-h-[44px]"
      :icon="Trash2"
      :loading="isRemoving"
      :label="$t('plan_overrides.ui.remove')"
      @click="$emit('remove')"
    />
  </div>
</template>
