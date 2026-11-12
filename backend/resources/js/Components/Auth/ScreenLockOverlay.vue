<script setup>
import { ref, toRef } from 'vue';
import { useScreenLockGuard } from '../../Composables/useScreenLockGuard';
import ScreenLockPanel from './ScreenLockPanel.vue';

const props = defineProps({
  visible: { type: Boolean, default: false },
});

const root = ref(null);
useScreenLockGuard(root, toRef(props, 'visible'));
</script>

<template>
  <!-- SET-02: above everything (modals, SweetAlert, the POS). A direct child of <body>, so the whole app can be made inert behind it. -->
  <Teleport to="body">
    <div
      v-if="visible"
      ref="root"
      class="fixed inset-0 z-[10000] overflow-y-auto bg-slate-100 font-tajawal text-slate-900 dark:bg-slate-950 dark:text-slate-100"
      style="background-image: radial-gradient(circle at 50% 0%, var(--color-primary-light), transparent 60%)"
      role="dialog"
      aria-modal="true"
      aria-labelledby="screen-lock-title"
      data-testid="screen-lock"
      @keydown.stop
      @keyup.stop
      @keypress.stop
    >
      <div class="flex min-h-full items-center justify-center p-4">
        <ScreenLockPanel />
      </div>
    </div>
  </Teleport>
</template>
