<template>
  <section
    class="bg-white dark:bg-slate-900/90 p-5 sm:p-6 rounded-3xl border shadow-sm dark:shadow-xl font-tajawal space-y-4"
    :class="
      archive?.state === 'failed' ? 'border-rose-300 dark:border-rose-800/70' : 'border-slate-200 dark:border-slate-800'
    "
    aria-labelledby="tenant-archive-panel-title"
  >
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <span
          class="w-10 h-10 rounded-2xl bg-zinc-500/10 border border-zinc-500/30 text-zinc-700 dark:text-zinc-300 flex items-center justify-center shrink-0"
        >
          <Archive class="w-5 h-5" />
        </span>
        <h2 id="tenant-archive-panel-title" class="text-base font-black text-slate-900 dark:text-white">
          {{ $t('tenant_archive.block_title') }}
        </h2>
      </div>
      <span
        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black border"
        :class="stateBadgeClass"
      >
        <Loader2 v-if="inProgress" class="w-3.5 h-3.5 animate-spin" />
        <span>{{ stateLabel }}</span>
      </span>
    </header>

    <p v-if="inProgress" class="text-xs font-bold text-amber-700 dark:text-amber-400">
      {{ $t('tenant_archive.in_progress_hint') }}
    </p>

    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
      <div v-for="row in timelineRows" :key="row.key" class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/40">
        <dt class="font-bold text-slate-500 dark:text-slate-400">{{ $t(row.label) }}</dt>
        <dd
          class="mt-1 font-black"
          :class="row.tone === 'danger' ? 'text-rose-700 dark:text-rose-400' : 'text-slate-900 dark:text-white'"
        >
          {{ row.value }}
        </dd>
      </div>
    </dl>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 p-4 space-y-3">
      <h3 class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-slate-200">
        <ShieldCheck class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
        <span>{{ $t('tenant_archive.final_backup') }}</span>
      </h3>
      <dl v-if="archive?.finalBackup" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
        <div v-for="row in backupRows" :key="row.key">
          <dt class="font-bold text-slate-500 dark:text-slate-400">{{ $t(row.label) }}</dt>
          <dd class="mt-1 font-black font-mono text-slate-900 dark:text-white break-all" dir="ltr">
            {{ row.value }}
          </dd>
        </div>
      </dl>
      <p v-else class="text-xs font-bold text-slate-500 dark:text-slate-400">
        {{ $t('tenant_archive.no_final_backup') }}
      </p>
    </div>

    <p class="flex items-start gap-2 text-[11px] font-bold text-slate-500 dark:text-slate-400">
      <Info class="w-4 h-4 shrink-0" />
      <span>{{ $t('tenant_archive.purge_cli_hint') }}</span>
    </p>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { Archive, Info, Loader2, ShieldCheck } from 'lucide-vue-next';
import { useTrans } from '../../Composables/useTrans';
import { useFormatters } from '../../Composables/useFormatters';

const props = defineProps({
  // normalizeArchiveBlock() output of useSuperAdminTenantShow, or null.
  archive: { type: Object, default: null },
  status: { type: String, default: '' },
});

const { t } = useTrans();
const { formatDateTime } = useFormatters();

// subscription.archive_error_codes.* (ArchiveTenantJob failure codes).
const KNOWN_ERROR_CODES = ['final_backup_failed', 'status_changed', 'purged', 'unknown'];

const inProgress = computed(() => ['queued', 'running'].includes(props.archive?.state));
const isPurged = computed(() => !!props.archive?.purgedAt);

const stateLabel = computed(() => {
  if (isPurged.value) return t('tenant_archive.state_purged');
  if (props.archive?.state) return t(`tenant_archive.states.${props.archive.state}`);
  if (props.status === 'archived') return t('tenant_archive.state_archived');
  return t('tenant_archive.not_set');
});

const stateBadgeClass = computed(() => {
  if (isPurged.value) return 'bg-slate-500/10 border-slate-500/30 text-slate-700 dark:text-slate-300';
  if (props.archive?.state === 'failed') return 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-400';
  if (inProgress.value) return 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-400';
  return 'bg-zinc-500/15 border-zinc-500/40 text-zinc-700 dark:text-zinc-300';
});

const dateOrNotSet = (value) => (value ? formatDateTime(value) : t('tenant_archive.not_set'));

const errorLabel = computed(() => {
  const code = props.archive?.errorCode;
  if (!code) return null;
  return t(`subscription.archive_error_codes.${KNOWN_ERROR_CODES.includes(code) ? code : 'unknown'}`);
});

const timelineRows = computed(() => {
  const archive = props.archive || {};
  const rows = [
    { key: 'requested', label: 'tenant_archive.requested_at', value: dateOrNotSet(archive.requestedAt) },
    { key: 'archived', label: 'tenant_archive.archived_at', value: dateOrNotSet(archive.archivedAt) },
    {
      key: 'eligible',
      label: 'tenant_archive.purge_eligible_at',
      value: archive.purgeEligibleAt ? formatDateTime(archive.purgeEligibleAt) : t('tenant_archive.purge_not_eligible'),
    },
  ];
  if (errorLabel.value) {
    rows.splice(1, 0, { key: 'error', label: 'tenant_archive.error_code', value: errorLabel.value, tone: 'danger' });
  }
  if (archive.purgedAt) {
    rows.push({ key: 'purged', label: 'tenant_archive.purged_at', value: formatDateTime(archive.purgedAt) });
  }
  return rows;
});

const backupRows = computed(() => {
  const backup = props.archive?.finalBackup || {};
  const orNotSet = (value) =>
    value === null || value === undefined || value === '' ? t('tenant_archive.not_set') : value;
  return [
    { key: 'id', label: 'tenant_archive.final_backup_id', value: orNotSet(backup.id) },
    { key: 'created', label: 'tenant_archive.final_backup_created_at', value: dateOrNotSet(backup.createdAt) },
    { key: 'sha', label: 'tenant_archive.final_backup_sha', value: orNotSet(backup.sha256Prefix) },
    { key: 'disk', label: 'tenant_archive.final_backup_disk', value: orNotSet(backup.disk) },
  ];
});
</script>
