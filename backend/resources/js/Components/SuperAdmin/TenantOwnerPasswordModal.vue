<script setup>
import { computed } from 'vue';
import { TriangleAlert } from 'lucide-vue-next';
import TemporaryPasswordModal from '../Common/TemporaryPasswordModal.vue';
import { useTrans } from '../../Composables/useTrans';

/** One-time reveal of the owner's temporary password, with who it belongs to (and the fallback warning). */
const props = defineProps({
  show: { type: Boolean, default: false },
  tenantName: { type: String, default: '' },
  owner: { type: Object, default: () => ({}) },
  matchedBy: { type: String, default: 'email' },
  password: { type: String, default: '' },
  canWhatsapp: { type: Boolean, default: false },
});

defineEmits(['close', 'whatsapp']);

const { t } = useTrans();

const rows = computed(() => [
  { key: 'name', label: t('super.owner_reset.name'), value: props.owner.name, ltr: false },
  { key: 'email', label: t('super.owner_reset.email'), value: props.owner.email, ltr: true },
  { key: 'phone', label: t('super.owner_reset.phone'), value: props.owner.phone, ltr: true },
]);
</script>

<template>
  <TemporaryPasswordModal
    :show="show"
    :title="$t('super.owner_reset.modal_title')"
    :subtitle="tenantName"
    :password="password"
    :login="owner.email || owner.phone || ''"
    :can-whatsapp="canWhatsapp && !!owner.phone"
    @close="$emit('close')"
    @whatsapp="$emit('whatsapp')"
  >
    <div
      v-if="matchedBy === 'fallback'"
      role="alert"
      data-testid="owner-fallback-warning"
      class="flex items-start gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs font-bold leading-relaxed text-rose-700 dark:text-rose-300"
    >
      <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
      <div class="space-y-1">
        <p class="font-black">{{ $t('super.owner_reset.fallback_title') }}</p>
        <p>{{ $t('super.owner_reset.fallback_text') }}</p>
      </div>
    </div>

    <dl
      class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 p-4 text-xs sm:grid-cols-3 dark:border-slate-800"
      data-testid="owner-details"
    >
      <div v-for="row in rows" :key="row.key" class="min-w-0 space-y-1">
        <dt class="font-black text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
        <dd class="break-all font-bold text-slate-900 dark:text-white" :dir="row.ltr ? 'ltr' : undefined">
          {{ row.value || '-' }}
        </dd>
      </div>
    </dl>
  </TemporaryPasswordModal>
</template>
