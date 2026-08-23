<template>
  <AppModal
    :show="show"
    :title="editingCategory ? ($t('inventory.edit_category') || 'تعديل الفئة') : ($t('inventory.add_category') || 'إضافة فئة جديدة')"
    :icon="Tag"
    max-width="md"
    @close="$emit('close')"
  >
    <form @submit.prevent="$emit('submit')" class="space-y-4 font-tajawal">
      <!-- Name -->
      <BaseInput
        v-model="form.name"
        :label="$t('inventory.category_name') || 'اسم الفئة'"
        :required="true"
        :placeholder="$t('inventory.category_name_placeholder') || 'مثال: مشروبات ساخنة، بن مطحون، حلويات'"
        :error="errors?.name"
      />

      <!-- Emoji / Icon Selector -->
      <div class="space-y-2">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
          {{ $t('inventory.category_icon_emoji') || 'أيقونة أو إيموجي الفئة' }}
        </label>
        <div class="flex items-center gap-2">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl shrink-0 shadow-2xs">
            {{ form.icon || '☕' }}
          </div>
          <BaseInput
            v-model="form.icon"
            placeholder="☕"
            input-class="h-12 text-center text-lg font-mono"
            wrapper-class="flex-1"
          />
        </div>

        <!-- Quick Emoji Presets Palette -->
        <div class="flex flex-wrap gap-1.5 pt-1">
          <button
            v-for="emoji in emojiPresets"
            :key="emoji"
            type="button"
            @click="form.icon = emoji"
            class="w-9 h-9 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-base transition active:scale-90 cursor-pointer shadow-2xs"
            :class="form.icon === emoji ? 'border-theme-primary ring-2 ring-theme-primary/30' : ''"
          >
            {{ emoji }}
          </button>
        </div>
      </div>

      <!-- Sort Order -->
      <BaseNumberInput
        v-model="form.sort_order"
        :label="$t('inventory.sort_order') || 'ترتيب الظهور في شريط الفئات'"
        :step="1"
        :min="0"
        :show-stepper="true"
      />

      <!-- Active Status -->
      <BaseSwitch
        v-model="form.is_active"
        :label="$t('common.status') || 'الحالة'"
        :description="$t('inventory.category_active_desc') || 'تفعيل ظهور هذه الفئة في شاشات الكاشير ونقاط البيع'"
      />

      <!-- Modal Footer Actions -->
      <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
        <BaseButton
          type="button"
          variant="ghost"
          size="md"
          :label="$t('common.cancel')"
          @click="$emit('close')"
        />

        <BaseButton
          type="submit"
          variant="gradient"
          size="md"
          :loading="isSubmitting"
          :label="editingCategory ? $t('common.save_changes') : ($t('inventory.create_category_btn') || 'إضافة الفئة')"
        />
      </div>
    </form>
  </AppModal>
</template>

<script setup>
import { Tag } from 'lucide-vue-next';
import AppModal from '../Common/AppModal.vue';
import BaseInput from '../Form/BaseInput.vue';
import BaseNumberInput from '../Form/BaseNumberInput.vue';
import BaseSwitch from '../Form/BaseSwitch.vue';
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false },
  editingCategory: { type: Object, default: null },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  isSubmitting: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);

const emojiPresets = [
  '☕', '🧃', '🍰', '🥪', '🍪', '🫘', '🥤', '🧊', '🎁', '📦', '🥐', '🥗', '🍕', '🍦', '🍨', '🍵'
];
</script>
