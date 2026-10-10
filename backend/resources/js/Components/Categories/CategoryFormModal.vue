<template>
  <AppModal
    :show="show"
    :title="editingCategory ? $t('inventory.edit_category') : $t('inventory.add_category')"
    :icon="Tag"
    max-width="md"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <!-- Name -->
      <BaseInput
        :model-value="form.name"
        @update:model-value="updateForm('name', $event)"
        :label="$t('inventory.category_name')"
        :required="true"
        :placeholder="$t('inventory.category_name_placeholder')"
        :error="errors?.name"
      />

      <!-- Lucide Icon Selector & Preset Palette -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            {{ $t('inventory.category_icon_emoji') }}
          </label>
          <div
            class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-theme-primary shadow-2xs"
          >
            <DynamicIcon :name="form.icon || 'Folder'" fallback="Folder" class="w-5 h-5 text-theme-primary" />
          </div>
        </div>

        <!-- Curated Lucide Presets Palette -->
        <div class="grid grid-cols-5 sm:grid-cols-10 gap-1.5 pt-1">
          <button
            v-for="item in iconPresets"
            :key="item.name"
            type="button"
            @click="updateForm('icon', item.name)"
            :aria-pressed="isCurrentIcon(item.name)"
            :aria-label="$t(item.labelKey)"
            class="min-h-[44px] rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-200 transition active:scale-90 cursor-pointer shadow-2xs"
            :class="
              isCurrentIcon(item.name)
                ? 'border-theme-primary ring-2 ring-theme-primary/30 text-theme-primary bg-theme-light'
                : ''
            "
          >
            <component :is="item.icon" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Sort Order -->
      <BaseNumberInput
        :model-value="form.sort_order"
        @update:model-value="updateForm('sort_order', $event)"
        :label="$t('inventory.sort_order')"
        :step="1"
        :min="0"
        :show-stepper="true"
      />

      <!-- Active Status -->
      <BaseSwitch
        :model-value="form.is_active"
        @update:model-value="updateForm('is_active', $event)"
        :label="$t('common.status')"
        :description="$t('inventory.category_active_desc')"
      />

      <!-- Modal Footer Actions -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton type="button" variant="ghost" size="md" :label="$t('common.cancel')" @click="$emit('close')" />

        <BaseButton
          type="submit"
          variant="gradient"
          size="md"
          :loading="isSubmitting"
          :label="editingCategory ? $t('common.save_changes') : $t('inventory.create_category_btn')"
        />
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import {
  Tag,
  Folder,
  Boxes,
  Package,
  Layers,
  ShoppingBag,
  ShoppingCart,
  Store,
  Sparkles,
  Star,
  Flame,
  Coffee,
  CupSoda,
  Utensils,
  Droplet,
  Leaf,
  Zap,
  Gift,
  Bookmark,
  Archive,
} from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import DynamicIcon from '../Common/DynamicIcon.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSwitch from '../Form/BaseSwitch.vue';
import BaseButton from '../Common/BaseButton.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  editingCategory: { type: Object, default: null },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit', 'update:form']);

// Never mutate the prop: emit a patched shallow copy and let the parent apply it.
const updateForm = (key, value) => emit('update:form', { ...props.form, [key]: value });

const iconPresets = [
  { name: 'Folder', icon: Folder, labelKey: 'expenses.icon_folder' },
  { name: 'Tag', icon: Tag, labelKey: 'expenses.icon_tag' },
  { name: 'Boxes', icon: Boxes, labelKey: 'expenses.icon_boxes' },
  { name: 'Package', icon: Package, labelKey: 'expenses.icon_package' },
  { name: 'Layers', icon: Layers, labelKey: 'expenses.icon_layers' },
  { name: 'ShoppingBag', icon: ShoppingBag, labelKey: 'expenses.icon_shopping_bag' },
  { name: 'ShoppingCart', icon: ShoppingCart, labelKey: 'expenses.icon_shopping_cart' },
  { name: 'Store', icon: Store, labelKey: 'expenses.icon_store' },
  { name: 'Sparkles', icon: Sparkles, labelKey: 'expenses.icon_sparkles' },
  { name: 'Star', icon: Star, labelKey: 'expenses.icon_star' },
  { name: 'Flame', icon: Flame, labelKey: 'expenses.icon_flame' },
  { name: 'Coffee', icon: Coffee, labelKey: 'expenses.icon_coffee' },
  { name: 'CupSoda', icon: CupSoda, labelKey: 'expenses.icon_cup_soda' },
  { name: 'Utensils', icon: Utensils, labelKey: 'expenses.icon_utensils' },
  { name: 'Droplet', icon: Droplet, labelKey: 'expenses.icon_droplet' },
  { name: 'Leaf', icon: Leaf, labelKey: 'expenses.icon_leaf' },
  { name: 'Zap', icon: Zap, labelKey: 'expenses.icon_zap' },
  { name: 'Gift', icon: Gift, labelKey: 'expenses.icon_gift' },
  { name: 'Bookmark', icon: Bookmark, labelKey: 'expenses.icon_bookmark' },
  { name: 'Archive', icon: Archive, labelKey: 'expenses.icon_archive' },
];

const isCurrentIcon = (name) => {
  const current = (props.form.icon || 'Folder').trim().toLowerCase();
  return current === name.toLowerCase();
};
</script>
