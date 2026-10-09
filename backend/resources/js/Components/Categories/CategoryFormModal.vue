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
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
          {{ $t('inventory.category_icon_emoji') }}
        </label>
        <div class="flex items-center gap-3">
          <div
            class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-theme-primary shrink-0 shadow-2xs"
          >
            <DynamicIcon :name="form.icon || 'Folder'" fallback="Folder" class="w-6 h-6 text-theme-primary" />
          </div>
          <BaseInput
            :model-value="form.icon || 'Folder'"
            @update:model-value="updateForm('icon', $event)"
            placeholder="Folder"
            input-class="h-12 text-center text-sm font-mono"
            wrapper-class="flex-1"
          />
        </div>

        <!-- Curated Lucide Presets Palette -->
        <div class="grid grid-cols-5 sm:grid-cols-10 gap-1.5 pt-1">
          <button
            v-for="item in iconPresets"
            :key="item.name"
            type="button"
            @click="updateForm('icon', item.name)"
            :title="item.name"
            :aria-label="item.name"
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
  { name: 'Folder', icon: Folder },
  { name: 'Tag', icon: Tag },
  { name: 'Boxes', icon: Boxes },
  { name: 'Package', icon: Package },
  { name: 'Layers', icon: Layers },
  { name: 'ShoppingBag', icon: ShoppingBag },
  { name: 'ShoppingCart', icon: ShoppingCart },
  { name: 'Store', icon: Store },
  { name: 'Sparkles', icon: Sparkles },
  { name: 'Star', icon: Star },
  { name: 'Flame', icon: Flame },
  { name: 'Coffee', icon: Coffee },
  { name: 'CupSoda', icon: CupSoda },
  { name: 'Utensils', icon: Utensils },
  { name: 'Droplet', icon: Droplet },
  { name: 'Leaf', icon: Leaf },
  { name: 'Zap', icon: Zap },
  { name: 'Gift', icon: Gift },
  { name: 'Bookmark', icon: Bookmark },
  { name: 'Archive', icon: Archive },
];

const isCurrentIcon = (name) => {
  const current = (props.form.icon || 'Folder').trim().toLowerCase();
  return current === name.toLowerCase();
};
</script>
