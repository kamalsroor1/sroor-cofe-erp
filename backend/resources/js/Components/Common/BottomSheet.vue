<script>
// Shared across every open sheet: only the top one reacts to Esc, and the page scroll stays locked until the last closes.
const openSheets = [];

const FOCUSABLE =
  'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
</script>

<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { X } from 'lucide-vue-next';
import { useNativeBridge } from '@/Composables/useNativeBridge';
import { useBodyScrollLock } from '@/Composables/useBodyScrollLock';
import { openSheetCount } from '@/Composables/useOpenSheets';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  // `tall` fills 90% of the viewport (lists, payment); `auto` hugs its content up to the same cap.
  size: { type: String, default: 'auto', validator: (v) => ['auto', 'tall'].includes(v) },
  closeable: { type: Boolean, default: true },
  testId: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const { triggerHaptic } = useNativeBridge();
const { lock: lockScroll, unlock: unlockScroll } = useBodyScrollLock();

const sheetId = useId();
const titleId = `${sheetId}-title`;
const panelRef = ref(null);
const dragOffset = ref(0);
let dragStartY = 0;
const isDragging = ref(false);
let previousFocus = null;
let isOpen = false;

const close = () => {
  if (!props.closeable) return;
  dragOffset.value = 0;
  emit('close');
};

const focusables = () =>
  Array.from(panelRef.value?.querySelectorAll(FOCUSABLE) ?? []).filter((el) => el.offsetParent !== null);

const onKeydown = (event) => {
  if (openSheets[openSheets.length - 1] !== sheetId) return;

  if (event.key === 'Escape') {
    // A nested modal that already consumed Esc keeps priority.
    if (event.defaultPrevented) return;
    event.preventDefault();
    close();
    return;
  }

  if (event.key !== 'Tab') return;
  const items = focusables();
  if (items.length === 0) {
    event.preventDefault();
    panelRef.value?.focus();
    return;
  }
  const first = items[0];
  const last = items[items.length - 1];
  const active = document.activeElement;
  if (event.shiftKey && (active === first || active === panelRef.value)) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && active === last) {
    event.preventDefault();
    first.focus();
  }
};

const onTouchStart = (event) => {
  if (!props.closeable) return;
  dragStartY = event.touches[0].clientY;
  isDragging.value = true;
};

const onTouchMove = (event) => {
  if (!isDragging.value) return;
  const diff = event.touches[0].clientY - dragStartY;
  dragOffset.value = diff > 0 ? diff : 0;
};

const onTouchEnd = () => {
  if (!isDragging.value) return;
  isDragging.value = false;
  if (dragOffset.value > 90) {
    triggerHaptic('medium');
    close();
  } else {
    dragOffset.value = 0;
  }
};

const onTouchCancel = () => {
  isDragging.value = false;
  dragOffset.value = 0;
};

const activate = () => {
  if (isOpen) return;
  isOpen = true;
  previousFocus = document.activeElement;
  openSheets.push(sheetId);
  openSheetCount.value = openSheets.length;
  lockScroll();
  document.addEventListener('keydown', onKeydown);
  nextTick(() => panelRef.value?.focus({ preventScroll: true }));
};

const deactivate = () => {
  if (!isOpen) return;
  isOpen = false;
  const index = openSheets.indexOf(sheetId);
  if (index !== -1) openSheets.splice(index, 1);
  openSheetCount.value = openSheets.length;
  unlockScroll();
  document.removeEventListener('keydown', onKeydown);
  isDragging.value = false;
  dragOffset.value = 0;
  if (previousFocus && typeof previousFocus.focus === 'function' && document.contains(previousFocus)) {
    previousFocus.focus({ preventScroll: true });
  }
  previousFocus = null;
};

watch(
  () => props.show,
  (visible) => (visible ? activate() : deactivate()),
  { immediate: true }
);

onBeforeUnmount(deactivate);
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet-slide">
      <div
        v-if="show"
        class="fixed inset-0 z-[45] flex items-end justify-center bg-black/70 backdrop-blur-xs font-tajawal select-none"
        :data-testid="testId || undefined"
        @click="close"
      >
        <div
          ref="panelRef"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="title ? titleId : undefined"
          tabindex="-1"
          class="w-full md:max-w-lg flex flex-col bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 rounded-t-3xl shadow-2xl text-slate-900 dark:text-white outline-none"
          :class="[
            size === 'tall' ? 'h-[90dvh]' : 'max-h-[90dvh]',
            { 'transition-transform duration-150 ease-out': !isDragging },
          ]"
          :style="dragOffset > 0 ? { transform: `translateY(${dragOffset}px)` } : {}"
          @click.stop
        >
          <!-- Drag handle: pulling it (or the header) down closes the sheet -->
          <div
            class="flex items-center justify-center pt-2 pb-1 shrink-0 touch-none"
            aria-hidden="true"
            @touchstart.passive="onTouchStart"
            @touchmove.passive="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchCancel"
          >
            <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700" />
          </div>

          <div
            v-if="title || $slots.header"
            class="flex items-center justify-between gap-2 px-4 pb-2 border-b border-slate-200 dark:border-slate-800 shrink-0"
            @touchstart.passive="onTouchStart"
            @touchmove.passive="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchCancel"
          >
            <slot name="header">
              <h3
                :id="titleId"
                class="font-black text-base text-slate-900 dark:text-white leading-tight min-w-0 truncate"
              >
                {{ title }}
              </h3>
            </slot>

            <div class="flex items-center gap-1.5 shrink-0">
              <slot name="actions" />
              <button
                v-if="closeable"
                type="button"
                class="w-11 h-11 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center transition active:scale-90 cursor-pointer"
                :aria-label="$t('common.close')"
                data-testid="bottom-sheet-close"
                @click="close"
              >
                <X class="w-5 h-5" aria-hidden="true" />
              </button>
            </div>
          </div>

          <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
            <slot />
          </div>

          <div
            v-if="$slots.footer"
            class="shrink-0 border-t border-slate-200 dark:border-slate-800 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom,0.75rem))]"
          >
            <slot name="footer" />
          </div>
          <div v-else class="shrink-0 pb-[env(safe-area-inset-bottom,0px)]" aria-hidden="true" />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
