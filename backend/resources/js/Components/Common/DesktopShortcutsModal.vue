<template>
  <AppModal
    :show="show"
    title="⌨️ دليل اختصارات الكيبورد (Desktop Hotkeys)"
    max-width="max-w-lg"
    @close="$emit('close')"
  >
    <div class="space-y-4 font-tajawal text-slate-800 dark:text-slate-100 p-1 select-none">
      <p class="text-xs text-slate-500 dark:text-slate-400 font-bold">
        تم تزويد المنظومة باختصارات سريعة لإلغاء استخدام الماوس بالكامل أثناء خدمة العمالك ونقاط البيع:
      </p>

      <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-bold">
        <!-- F1 -->
        <div class="py-2.5 flex items-center justify-between">
          <span class="text-slate-700 dark:text-slate-300">فتح نافذة المساعدة والاختصارات الحالية</span>
          <kbd class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-theme-primary text-xs shadow-xs">F1</kbd>
        </div>

        <!-- F2 -->
        <div class="py-2.5 flex items-center justify-between">
          <span class="text-slate-700 dark:text-slate-300">التركيز الفوري على خانة البحث والباركود (POS)</span>
          <kbd class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-emerald-500 text-xs shadow-xs">F2</kbd>
        </div>

        <!-- F9 / Ctrl+Enter -->
        <div class="py-2.5 flex items-center justify-between">
          <span class="text-slate-700 dark:text-slate-300">حفظ الفاتورة والطباعة الحرارية الصامتة الفورية</span>
          <div class="flex items-center gap-1.5">
            <kbd class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-emerald-500 text-xs">F9</kbd>
            <span class="text-slate-400">أو</span>
            <kbd class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-emerald-500 text-xs">Ctrl+Enter</kbd>
          </div>
        </div>

        <!-- F12 -->
        <div class="py-2.5 flex items-center justify-between">
          <span class="text-slate-700 dark:text-slate-300">فتح درج النقدية يدويًا (Drawer Kick)</span>
          <kbd class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-amber-500 text-xs shadow-xs">F12</kbd>
        </div>

        <!-- F11 -->
        <div class="py-2.5 flex items-center justify-between">
          <span class="text-slate-700 dark:text-slate-300">التبديل لوضع الشاشة الكاملة (Fullscreen)</span>
          <kbd class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-slate-700 dark:text-slate-300 text-xs shadow-xs">F11</kbd>
        </div>

        <!-- Ctrl+Shift+K -->
        <div class="py-2.5 flex items-center justify-between">
          <span class="text-slate-700 dark:text-slate-300">تأمين وقفل وضع الكاشير (Kiosk Mode)</span>
          <kbd class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-rose-500 text-xs shadow-xs">Ctrl+Shift+K</kbd>
        </div>

        <!-- Ctrl+R / F5 -->
        <div class="py-2.5 flex items-center justify-between">
          <span class="text-slate-700 dark:text-slate-300">إعادة تحميل الصفحة بسلاسة (Reload)</span>
          <kbd class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 font-mono font-black text-slate-700 dark:text-slate-300 text-xs shadow-xs">Ctrl+R</kbd>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end w-full">
        <BaseButton variant="primary" @click="$emit('close')">
          {{ $t('common.done') }}
        </BaseButton>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import AppModal from './AppModal.vue';
import BaseButton from './BaseButton.vue';

defineProps({
  show: { type: Boolean, default: false }
});

defineEmits(['close']);
</script>
