<template>
  <AppModal
    :show="editor.show.value"
    max-width="4xl"
    :title="title"
    :subtitle="$t('super.plan_editor_subtitle')"
    @close="editor.close()"
  >
    <form id="plan-editor-form" novalidate class="space-y-4 text-xs font-tajawal" @submit.prevent="editor.submit()">
      <div
        class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1 border-b border-slate-200 dark:border-slate-800"
        role="tablist"
      >
        <button
          v-for="tab in editor.tabs.value"
          :key="tab"
          type="button"
          role="tab"
          :aria-selected="editor.activeTab.value === tab"
          class="relative shrink-0 min-h-[44px] px-4 rounded-xl text-xs font-black transition cursor-pointer"
          :class="
            editor.activeTab.value === tab
              ? 'bg-theme-primary text-white shadow-sm'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
          "
          @click="editor.setTab(tab)"
        >
          {{ tabLabel(tab) }}
          <span
            v-if="editor.tabsWithErrors.value.has(tab)"
            class="absolute top-1.5 end-1.5 w-2 h-2 rounded-full bg-rose-500"
            aria-hidden="true"
          />
        </button>
      </div>

      <div role="tabpanel" class="min-h-[280px]">
        <PlanBasicsSection
          v-show="editor.activeTab.value === 'basics'"
          :form="editor.form.value"
          :errors="editor.errors.value"
          @update:field="editor.setField"
        />
        <PlanPricesSection
          v-show="editor.activeTab.value === 'prices'"
          :form="editor.form.value"
          :tax-rate="editor.taxRate.value"
          :errors="editor.errors.value"
          @update:field="editor.setField"
        />
        <PlanPromotionSection
          v-show="editor.activeTab.value === 'promotion'"
          :form="editor.form.value"
          :errors="editor.errors.value"
          @update:field="editor.setField"
          @clear="editor.clearOffer()"
        />
        <PlanLimitsSection
          v-show="editor.activeTab.value === 'limits'"
          :form="editor.form.value"
          :errors="editor.errors.value"
          @update:field="editor.setField"
        />
        <PlanFeaturesSection
          v-show="editor.activeTab.value === 'features'"
          :form="editor.form.value"
          :errors="editor.errors.value"
          :registry="registry"
          @update:feature="editor.setFeature"
        />
        <PlanAddonsSection
          v-show="editor.activeTab.value === 'addons'"
          :form="editor.form.value"
          :errors="editor.errors.value"
          :catalog="catalog"
          @update:addon="editor.setAddon"
        />
        <PlanHistorySection
          v-if="editor.activeTab.value === historyTab && editor.editingPlan.value"
          :plan-id="editor.editingPlan.value.id"
        />
      </div>
    </form>

    <template #footer>
      <div class="flex items-center justify-end gap-3">
        <BaseButton type="button" variant="secondary" size="md" @click="editor.close()">
          {{ $t('common.cancel') }}
        </BaseButton>
        <BaseButton
          type="submit"
          form="plan-editor-form"
          variant="primary"
          size="md"
          :loading="editor.isSubmitting.value"
          class="shadow-lg shadow-theme-primary font-black"
        >
          {{ editor.isSubmitting.value ? $t('common.loading') : $t('common.save') }}
        </BaseButton>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppModal from '../../Common/AppModal.vue';
import BaseButton from '../../Common/BaseButton.vue';
import PlanBasicsSection from './PlanBasicsSection.vue';
import PlanPricesSection from './PlanPricesSection.vue';
import PlanPromotionSection from './PlanPromotionSection.vue';
import PlanLimitsSection from './PlanLimitsSection.vue';
import PlanFeaturesSection from './PlanFeaturesSection.vue';
import PlanAddonsSection from './PlanAddonsSection.vue';
import PlanHistorySection from './PlanHistorySection.vue';
import { PLAN_HISTORY_TAB } from '../../../helpers/planEditor';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  // The object returned by usePlanEditor().
  editor: { type: Object, required: true },
  registry: { type: Array, default: () => [] },
  catalog: { type: Array, default: () => [] },
});

const { t } = useTrans();
const historyTab = PLAN_HISTORY_TAB;

const tabLabel = (tab) => (tab === historyTab ? t('plan_console.history.tab') : t(`super.plan_tab_${tab}`));

const title = computed(() =>
  props.editor.isEditing.value
    ? t('super.edit_plan_modal_title', { name: props.editor.editingPlan.value.name })
    : t('super.create_plan_modal_title')
);
</script>
