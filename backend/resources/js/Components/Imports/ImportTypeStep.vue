<script setup>
import { Package, Users, Truck, Tags, ArrowLeft } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import EmptyState from '../Common/EmptyState.vue';
import ImportStoreNotice from './ImportStoreNotice.vue';

defineProps({
  allowedTypes: { type: Array, required: true },
  selected: { type: String, default: null },
  store: { type: Object, default: null },
});

defineEmits(['select', 'continue']);

const icons = { items: Package, customers: Users, suppliers: Truck, categories: Tags };
</script>

<template>
  <section class="space-y-4 font-tajawal">
    <EmptyState v-if="allowedTypes.length === 0" :title="$t('import.ui.no_types')" />
    <template v-else>
      <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $t('import.ui.choose_type') }}</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button
          v-for="key in allowedTypes"
          :key="key"
          type="button"
          class="min-h-[88px] p-4 rounded-2xl border-2 text-start flex items-start gap-3 bg-white dark:bg-slate-900 transition active:scale-[0.99] cursor-pointer"
          :class="selected === key ? '' : 'border-slate-200 dark:border-slate-800'"
          :style="
            selected === key ? { borderColor: 'var(--color-primary)', background: 'var(--color-primary-light)' } : null
          "
          :aria-pressed="selected === key"
          @click="$emit('select', key)"
        >
          <span
            class="w-11 h-11 shrink-0 rounded-xl flex items-center justify-center bg-slate-100 dark:bg-slate-800"
            :style="selected === key ? { color: 'var(--color-primary)' } : null"
          >
            <component :is="icons[key]" class="w-5 h-5" />
          </span>
          <span class="space-y-1 min-w-0">
            <span class="block font-black text-sm text-slate-900 dark:text-white">{{
              $t(`import.ui.types.${key}.title`)
            }}</span>
            <span class="block text-xs font-bold text-slate-500 dark:text-slate-400">{{
              $t(`import.ui.types.${key}.desc`)
            }}</span>
          </span>
        </button>
      </div>

      <ImportStoreNotice :store="store" :show-stock="selected === 'items'" />

      <div class="flex justify-end">
        <BaseButton
          variant="primary"
          size="lg"
          :disabled="!selected || !store"
          :label="$t('import.ui.continue')"
          :trailing-icon="ArrowLeft"
          trailing-icon-class="rtl:rotate-0 ltr:rotate-180"
          class="min-h-[44px]"
          @click="$emit('continue')"
        />
      </div>
    </template>
  </section>
</template>
