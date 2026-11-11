<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { ArrowLeft, ChevronDown, X } from 'lucide-vue-next';
import LandingLogo from './LandingLogo.vue';
import LandingLocaleToggle from './LandingLocaleToggle.vue';
import LandingThemeToggle from './LandingThemeToggle.vue';
import MarketingButton from './MarketingButton.vue';
import MarketingIconBadge from './MarketingIconBadge.vue';
import { useLandingNav } from '../../Composables/useLandingNav';

const props = defineProps({
  open: { type: Boolean, default: false },
  brandName: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const { links, activeKey, industryRoute, industryLabelKey } = useLandingNav();

const panel = ref(null);
const closeButton = ref(null);
const industriesOpen = ref(false);

const FOCUSABLE = 'a[href], button:not([disabled])';

// Esc closes (focus goes back to the menu button, handled by the parent); Tab stays inside the dialog.
const onKeydown = (event) => {
  if (event.key === 'Escape') {
    emit('close', { restoreFocus: true });
    return;
  }
  if (event.key !== 'Tab' || !panel.value) return;
  const items = [...panel.value.querySelectorAll(FOCUSABLE)];
  if (!items.length) return;
  const first = items[0];
  const last = items[items.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
};

watch(
  () => props.open,
  async (open) => {
    if (open) {
      document.addEventListener('keydown', onKeydown);
      await nextTick();
      closeButton.value?.focus();
    } else {
      document.removeEventListener('keydown', onKeydown);
      industriesOpen.value = false;
    }
  }
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div
      v-if="open"
      id="landing-mobile-menu"
      ref="panel"
      role="dialog"
      aria-modal="true"
      :aria-label="$t('landing.nav.main')"
      class="fixed inset-0 z-50 flex flex-col gap-1.5 overflow-y-auto overscroll-contain bg-mk-bg px-5 pt-4 pb-6 lg:hidden"
    >
      <div class="mb-2 flex items-center justify-between gap-3">
        <LandingLogo :name="brandName" :size="30" />
        <button
          ref="closeButton"
          type="button"
          class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-mk-line bg-mk-surface text-mk-ink focus-visible:outline-2 focus-visible:outline-mk-primary active:scale-95"
          :aria-label="$t('landing.nav.close_menu')"
          @click="emit('close', { restoreFocus: true })"
        >
          <X class="size-[22px]" aria-hidden="true" />
        </button>
      </div>

      <nav :aria-label="$t('landing.nav.main')">
        <ul>
          <li v-for="link in links" :key="link.key" class="border-b border-mk-line">
            <template v-if="link.children">
              <button
                type="button"
                class="flex min-h-16 w-full cursor-pointer items-center gap-3 px-1 py-3 text-start text-[17px] font-bold text-mk-ink"
                :aria-expanded="industriesOpen"
                aria-controls="landing-mobile-industries"
                @click="industriesOpen = !industriesOpen"
              >
                <MarketingIconBadge :icon="link.icon" :size="40" />
                <span class="flex-1">{{ $t(link.labelKey) }}</span>
                <ChevronDown
                  class="size-[18px] text-mk-faint transition-transform"
                  :class="{ 'rotate-180': industriesOpen }"
                  aria-hidden="true"
                />
              </button>
              <ul v-show="industriesOpen" id="landing-mobile-industries" class="pb-2 ps-[52px]">
                <li v-for="item in link.children" :key="item.key">
                  <router-link
                    :to="industryRoute(item.key)"
                    class="flex min-h-11 items-center gap-2.5 rounded-xl px-2 text-[15px] font-medium text-mk-muted"
                    active-class="font-bold text-mk-ink"
                  >
                    <component :is="item.icon" class="size-4 text-mk-primary" aria-hidden="true" />
                    {{ $t(industryLabelKey(item.key)) }}
                  </router-link>
                </li>
              </ul>
            </template>
            <router-link
              v-else
              :to="link.to"
              class="flex min-h-16 items-center gap-3 px-1 py-3 text-[17px] font-bold text-mk-ink"
              :aria-current="activeKey === link.key ? 'page' : undefined"
            >
              <MarketingIconBadge :icon="link.icon" :size="40" />
              <span class="flex-1">{{ $t(link.labelKey) }}</span>
              <ArrowLeft class="size-[18px] text-mk-faint ltr:rotate-180" aria-hidden="true" />
            </router-link>
          </li>
        </ul>
      </nav>

      <div class="mt-2 flex flex-wrap gap-2">
        <LandingLocaleToggle variant="pill" />
        <LandingThemeToggle variant="pill" />
      </div>
      <MarketingButton to="/signup" size="lg" :icon="ArrowLeft" block class="mt-2">
        {{ $t('landing.request_trial') }}
      </MarketingButton>
      <MarketingButton :to="{ name: 'login' }" variant="outline" size="lg" block>
        {{ $t('landing.nav.login') }}
      </MarketingButton>
    </div>
  </Transition>
</template>
