<script setup>
import { computed, onMounted } from 'vue';
import { ArrowLeft, Check, Mail, Phone } from 'lucide-vue-next';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import MarketingButton from '../Landing/MarketingButton.vue';
import SignupStatusCard from './SignupStatusCard.vue';
import { PROVISIONING_PHASES as PHASES, useSignupProvisioning } from '../../Composables/useSignupProvisioning';

const props = defineProps({
  token: { type: String, default: '' },
  emailMasked: { type: String, default: '' },
  /** Set when the verified email already owns a shop: nothing to wait for, only the explanation. */
  refusedMessage: { type: String, default: '' },
  /** brand block of GET /public/plans (support_phone / support_email are optional). */
  brand: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['finished', 'start-over']);

const {
  phase,
  loginUrl,
  businessName,
  emailMasked: serverEmail,
  start,
} = useSignupProvisioning(
  () => props.token,
  () => emit('finished')
);

const email = computed(() => serverEmail.value || props.emailMasked);
const isRefused = computed(() => props.refusedMessage !== '');
const supports = computed(() =>
  [
    props.brand?.support_phone && {
      key: 'phone',
      icon: Phone,
      value: props.brand.support_phone,
      href: `tel:${props.brand.support_phone}`,
    },
    props.brand?.support_email && {
      key: 'email',
      icon: Mail,
      value: props.brand.support_email,
      href: `mailto:${props.brand.support_email}`,
    },
  ].filter(Boolean)
);

onMounted(() => {
  if (!isRefused.value) start();
});
</script>

<template>
  <SignupStatusCard
    v-if="isRefused"
    tone="danger"
    :title="$t('self_signup.ui.provisioning.failed_title')"
    :body="refusedMessage"
  >
    <template v-for="support in supports" :key="support.key">
      <MarketingButton :href="support.href" variant="outline" :icon="support.icon">
        <span dir="ltr">{{ support.value }}</span>
      </MarketingButton>
    </template>
    <MarketingButton variant="primary" @click="emit('start-over')">{{
      $t('self_signup.ui.otp.start_over')
    }}</MarketingButton>
  </SignupStatusCard>

  <SignupStatusCard
    v-else-if="phase === PHASES.ready"
    :title="$t('self_signup.ui.provisioning.ready_title')"
    :body="$t('self_signup.ui.provisioning.ready_body', { shop: businessName })"
  >
    <MarketingButton :href="loginUrl" size="lg" :icon="ArrowLeft">
      {{ $t('self_signup.ui.provisioning.open_login') }}
    </MarketingButton>
  </SignupStatusCard>

  <SignupStatusCard
    v-else-if="phase === PHASES.queued || phase === PHASES.delayed"
    tone="info"
    :title="$t(`self_signup.ui.provisioning.${phase === PHASES.queued ? 'queued' : 'delayed'}_title`)"
    :body="$t(`self_signup.ui.provisioning.${phase === PHASES.queued ? 'queued' : 'delayed'}_body`, { email })"
  >
    <MarketingButton v-if="phase === PHASES.delayed" variant="primary" @click="start">
      {{ $t('self_signup.ui.provisioning.check_again') }}
    </MarketingButton>
    <MarketingButton to="/welcome" variant="outline">{{ $t('self_signup.ui.provisioning.back_home') }}</MarketingButton>
  </SignupStatusCard>

  <SignupStatusCard
    v-else-if="phase === PHASES.failed"
    tone="danger"
    :title="$t('self_signup.ui.provisioning.failed_title')"
    :body="$t('self_signup.ui.provisioning.failed_body')"
  >
    <template v-for="support in supports" :key="support.key">
      <MarketingButton :href="support.href" variant="primary" :icon="support.icon">
        <span dir="ltr">{{ support.value }}</span>
      </MarketingButton>
    </template>
    <MarketingButton to="/welcome" variant="outline">{{ $t('self_signup.ui.provisioning.back_home') }}</MarketingButton>
  </SignupStatusCard>

  <!-- Working: skeleton rows mirror the three real steps; never a lone spinner. -->
  <div
    v-else
    role="status"
    aria-live="polite"
    class="mx-auto flex w-full max-w-[560px] flex-col gap-6 rounded-3xl border border-mk-line bg-mk-surface p-7 mk-shadow-card sm:p-10"
  >
    <header class="flex flex-col gap-2 text-center">
      <h1 class="text-[26px] font-bold text-mk-ink sm:text-[30px]">{{ $t('self_signup.ui.provisioning.title') }}</h1>
      <p class="text-base leading-[1.6] text-mk-muted">{{ $t('self_signup.ui.provisioning.subtitle') }}</p>
    </header>
    <ol class="flex flex-col gap-4">
      <li class="flex items-center gap-3">
        <span
          class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-mk-primary text-mk-primary-ink"
        >
          <Check class="size-5" stroke-width="3" aria-hidden="true" />
        </span>
        <span class="text-base font-bold text-mk-ink">{{ $t('self_signup.ui.provisioning.step_verified') }}</span>
      </li>
      <li class="flex items-center gap-3">
        <Skeleton width="size-9" height="" rounded="rounded-full" class-name="shrink-0" />
        <div class="flex min-w-0 flex-1 flex-col gap-2">
          <span class="text-base font-bold text-mk-ink">{{ $t('self_signup.ui.provisioning.step_creating') }}</span>
          <Skeleton height="h-2" />
        </div>
      </li>
      <li class="flex items-center gap-3 opacity-70">
        <Skeleton width="size-9" height="" rounded="rounded-full" class-name="shrink-0" />
        <div class="flex min-w-0 flex-1 flex-col gap-2">
          <span class="text-base font-medium text-mk-muted">{{ $t('self_signup.ui.provisioning.step_ready') }}</span>
          <Skeleton height="h-2" width="w-2/3" />
        </div>
      </li>
    </ol>
  </div>
</template>
