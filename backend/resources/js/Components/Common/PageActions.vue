<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { MoreHorizontal } from 'lucide-vue-next';
import BaseButton from './BaseButton.vue';
import BaseDropdown from './BaseDropdown.vue';
import { useMediaQuery } from '../../Composables/useMediaQuery';

// Header actions. From `sm` up every action is a button; on phones only the primary action stays visible
// and the rest move into one overflow menu. Item shape:
// { key, label, icon, iconClass, to, onClick, primary, variant, active, badge, show }.
// An item with `children` (same shape, minus primary) renders as a dropdown button, and on phones its children
// are flattened into the overflow menu.
const props = defineProps({
  items: { type: Array, default: () => [] },
});

const PHONE_QUERY = '(max-width: 639.98px)';
const isPhone = useMediaQuery(PHONE_QUERY);
const router = useRouter();

const visibleItems = computed(() => props.items.filter((item) => item.show !== false));
const primaryItem = computed(() => visibleItems.value.find((item) => item.primary) || null);
const toMenuItem = (item) => ({
  label: item.label,
  icon: item.icon,
  iconColor: item.iconClass,
  danger: item.variant === 'danger',
  onClick: () => (item.to ? router.push(item.to) : item.onClick?.()),
});
const overflowItems = computed(() =>
  visibleItems.value
    .filter((item) => item !== primaryItem.value)
    .flatMap((item) => (item.children ? item.children.filter((child) => child.show !== false) : [item]))
    .map(toMenuItem)
);

const buttonProps = (item) => ({
  to: item.to || null,
  variant: item.variant || (item.primary ? 'gradient' : 'default'),
  icon: item.icon,
  iconClass: item.iconClass || '',
  label: item.label,
  active: Boolean(item.active),
  badge: item.badge ?? null,
});
</script>

<template>
  <div class="flex items-center gap-2 flex-wrap" data-testid="page-actions">
    <template v-if="isPhone">
      <BaseButton
        v-if="primaryItem"
        v-bind="buttonProps(primaryItem)"
        :data-testid="primaryItem.key ? `page-action-${primaryItem.key}` : undefined"
        @click="primaryItem.onClick?.()"
      />
      <BaseDropdown v-if="overflowItems.length" align="end" :items="overflowItems" menu-class="w-60">
        <template #trigger="{ toggle, isOpen }">
          <BaseButton
            variant="default"
            size="icon"
            :icon="MoreHorizontal"
            :aria-label="$t('common.more')"
            data-testid="page-actions-more"
            :active="isOpen"
            @click="toggle"
          />
        </template>
      </BaseDropdown>
    </template>
    <template v-else>
      <template v-for="(item, index) in visibleItems" :key="item.key || index">
        <BaseDropdown
          v-if="item.children"
          :label="item.label"
          :icon="item.icon"
          :icon-class="item.iconClass"
          align="end"
          :items="item.children.filter((child) => child.show !== false).map(toMenuItem)"
        />
        <BaseButton
          v-else
          v-bind="buttonProps(item)"
          :data-testid="item.key ? `page-action-${item.key}` : undefined"
          @click="item.onClick?.()"
        />
      </template>
    </template>
  </div>
</template>
