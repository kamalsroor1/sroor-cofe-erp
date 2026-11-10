<script setup>
import { computed } from 'vue';
import AppModal from '../Common/AppModal.vue';
import { useTrans } from '../../Composables/useTrans';

const props = defineProps({
  show: { type: Boolean, default: false },
});

defineEmits(['close']);

const { t } = useTrans();

// F7 is listed only once the POS actually renders a discount input.
const hasDiscountInput = computed(() => props.show && !!document.querySelector('[data-pos-discount-input]'));

const groups = computed(() => [
  {
    title: t('pos.shortcuts_group_general'),
    rows: [
      { keys: ['F2'], label: t('pos.shortcut_search') },
      { keys: ['F3'], label: t('pos.shortcut_customer') },
      { keys: ['F4'], label: t('pos.shortcut_new_order') },
      ...(hasDiscountInput.value ? [{ keys: ['F7'], label: t('pos.shortcut_discount') }] : []),
      { keys: ['F8'], label: t('pos.shortcut_recent_sales') },
      { keys: ['F9', 'Ctrl+Enter'], label: t('pos.shortcut_checkout') },
      { keys: ['F10'], label: t('pos.shortcut_catalog') },
      { keys: ['F1', '?'], label: t('pos.shortcut_help') },
      { keys: ['Esc'], label: t('pos.shortcut_escape') },
      { keys: ['3*'], label: t('pos.shortcut_multiplier') },
    ],
  },
  {
    title: t('pos.shortcuts_group_lines'),
    rows: [
      { keys: ['Alt+Q'], label: t('pos.shortcut_edit_qty') },
      { keys: ['Alt+P'], label: t('pos.shortcut_edit_price') },
      { keys: ['Alt+D'], label: t('pos.shortcut_edit_discount') },
      { keys: ['Alt+L'], label: t('pos.shortcut_last_line') },
      { keys: ['Tab', 'Shift+Tab'], label: t('pos.shortcut_tab_fields') },
      { keys: ['Enter'], label: t('pos.shortcut_enter_field') },
      { keys: ['Esc'], label: t('pos.shortcut_revert_field') },
    ],
  },
]);
</script>

<template>
  <AppModal :show="show" :title="$t('pos.shortcuts_title')" max-width="lg" @close="$emit('close')">
    <div class="p-4 space-y-5 font-tajawal select-none">
      <section v-for="group in groups" :key="group.title" class="space-y-2">
        <h3 class="text-xs font-black text-slate-500 dark:text-slate-400">{{ group.title }}</h3>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
          <li v-for="row in group.rows" :key="row.label" class="flex items-center justify-between gap-3 py-2">
            <span class="text-sm text-slate-700 dark:text-slate-200">{{ row.label }}</span>
            <span class="flex items-center gap-1 shrink-0" dir="ltr">
              <kbd
                v-for="key in row.keys"
                :key="key"
                class="px-2 py-1 text-xs font-mono font-bold rounded-lg border bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700"
              >
                {{ key }}
              </kbd>
            </span>
          </li>
        </ul>
      </section>
      <p class="text-[11px] text-slate-500">{{ $t('pos.shortcuts_hint') }}</p>
    </div>
  </AppModal>
</template>
