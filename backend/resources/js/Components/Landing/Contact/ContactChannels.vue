<script setup>
import { computed } from 'vue';
import { ArrowLeft, Info, Mail, MessageCircle, Phone, Sparkles } from 'lucide-vue-next';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import MarketingButton from '../MarketingButton.vue';
import ContactCard from './ContactCard.vue';

const props = defineProps({
  /** brand block of GET /public/plans: support_phone / support_email are optional platform settings. */
  brand: { type: Object, default: () => ({}) },
  isLoading: { type: Boolean, default: false },
});

const phone = computed(() => String(props.brand.support_phone ?? '').trim());
const email = computed(() => String(props.brand.support_email ?? '').trim());

// The platform setting may hold a number or a full WhatsApp URL (see useSupportContact).
const phoneIsUrl = computed(() => /^https?:\/\//i.test(phone.value));
const phoneDigits = computed(() => (phoneIsUrl.value ? '' : phone.value.replace(/\D/g, '')));
const telHref = computed(() => (phoneDigits.value ? `tel:${phone.value.replace(/[^\d+]/g, '')}` : ''));
const whatsappHref = computed(() => {
  if (phoneIsUrl.value) return phone.value;
  return phoneDigits.value ? `https://wa.me/${phoneDigits.value}` : '';
});

const hasPhone = computed(() => Boolean(telHref.value || whatsappHref.value));
const hasEmail = computed(() => email.value.includes('@'));
const count = computed(() => 1 + Number(hasPhone.value) + Number(hasEmail.value));
const WIDTH = { 1: 'max-w-[560px]', 2: 'max-w-[820px]', 3: 'max-w-[1200px]' };
const COLUMNS = { 1: '', 2: 'md:grid-cols-2', 3: 'md:grid-cols-3' };
</script>

<template>
  <section :aria-label="$t('marketing.contact.channels_label')" class="bg-mk-bg px-5 pt-8 pb-4 sm:px-6 lg:pt-16">
    <div v-if="isLoading" class="mx-auto grid max-w-[1200px] gap-3 md:grid-cols-3 lg:gap-6" aria-busy="true">
      <div v-for="n in 3" :key="n" class="flex flex-col gap-3.5 rounded-3xl border border-mk-line bg-mk-surface p-7">
        <Skeleton width="w-[52px]" height="h-[52px]" rounded="rounded-[14px]" />
        <Skeleton width="w-1/2" height="h-5" />
        <Skeleton width="w-3/4" height="h-6" />
        <Skeleton height="h-[46px]" rounded="rounded-[14px]" />
      </div>
    </div>

    <div v-else class="mx-auto flex flex-col items-center gap-4" :class="WIDTH[count]">
      <ul class="grid w-full gap-3 lg:gap-6" :class="COLUMNS[count]">
        <li v-if="hasPhone">
          <ContactCard :icon="Phone" :title="$t('marketing.contact.phone.title')" :value="phoneIsUrl ? '' : phone">
            <MarketingButton v-if="telHref" :href="telHref" variant="outline" class="flex-1">
              {{ $t('marketing.contact.phone.cta') }}
            </MarketingButton>
            <MarketingButton
              v-if="whatsappHref"
              :href="whatsappHref"
              variant="outline"
              :icon="MessageCircle"
              :flip-icon="false"
              target="_blank"
              rel="noopener"
              class="flex-1"
            >
              {{ $t('marketing.contact.phone.whatsapp') }}
            </MarketingButton>
          </ContactCard>
        </li>
        <li v-if="hasEmail">
          <ContactCard :icon="Mail" :title="$t('marketing.contact.email.title')" :value="email">
            <MarketingButton :href="`mailto:${email}`" variant="outline" block>
              {{ $t('marketing.contact.email.cta') }}
            </MarketingButton>
          </ContactCard>
        </li>
        <li>
          <ContactCard
            :icon="Sparkles"
            :title="$t('marketing.contact.trial.title')"
            :body="$t('marketing.contact.trial.body')"
          >
            <MarketingButton to="/signup" :icon="ArrowLeft" block>{{ $t('landing.request_trial') }}</MarketingButton>
          </ContactCard>
        </li>
      </ul>
      <p v-if="count === 1" class="flex items-start gap-2 text-center text-sm leading-[1.6] text-mk-muted">
        <Info class="mt-0.5 size-4 shrink-0 text-mk-primary" aria-hidden="true" />
        {{ $t('marketing.contact.no_channels') }}
      </p>
    </div>
  </section>
</template>
