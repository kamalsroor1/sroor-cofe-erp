<template>
  <div
    class="bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl overflow-hidden font-tajawal"
  >
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="6" />
    </div>

    <div v-else-if="tenants.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs">
          <thead
            class="bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 font-bold"
          >
            <tr>
              <th class="p-4 text-start font-tajawal">{{ $t('super.tenant_org_col') }}</th>
              <th class="p-4 text-start font-tajawal">{{ $t('super.domain_path_col') }}</th>
              <th class="p-4 text-start font-tajawal">{{ $t('super.subscribed_plan_col') }}</th>
              <th class="p-4 text-start font-tajawal">{{ $t('super.email_admin_col') }}</th>
              <th class="p-4 text-center font-tajawal">{{ $t('common.status') }}</th>
              <th class="p-4 text-end font-tajawal">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-mono">
            <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
              <td class="p-4 font-sans font-bold text-slate-900 dark:text-white font-tajawal">
                <router-link
                  :to="`/super-admin/tenants/${t.id}`"
                  class="text-sm hover:text-purple-500 hover:underline flex items-center gap-1.5 font-black"
                >
                  <span>{{ t.name }}</span>
                  <span class="text-xs text-purple-400">↗</span>
                </router-link>
                <div class="text-[10px] text-slate-400 font-mono">ID: {{ t.id }}</div>
              </td>

              <td class="p-4 text-cyan-600 dark:text-cyan-400 font-mono">
                <a
                  :href="`http://${t.domain}`"
                  target="_blank"
                  class="hover:underline flex items-center gap-1 font-bold"
                >
                  <span>{{ t.domain }}</span>
                  <ExternalLink class="w-3 h-3" />
                </a>
              </td>

              <td class="p-4 font-sans">
                <span
                  class="px-2.5 py-1 bg-purple-500/10 border border-purple-500/30 text-purple-600 dark:text-purple-400 rounded-full font-bold"
                >
                  {{ t.plan_name }}
                </span>
              </td>

              <td class="p-4 text-slate-700 dark:text-slate-300 font-mono">
                <div>{{ t.email }}</div>
                <div class="text-[10px] text-slate-400">{{ t.phone || $t('super.no_phone') }}</div>
              </td>

              <td class="p-4 text-center font-sans">
                <button
                  type="button"
                  @click="$emit('open-status', t)"
                  class="min-h-11 px-3 inline-flex items-center rounded-full text-[11px] font-bold border transition cursor-pointer active:scale-95"
                  :class="tenantStatusBadgeClass(t.status)"
                >
                  {{ tenantStatusLabel(t.status, $t) }}
                </button>
              </td>

              <td class="p-4 text-end font-sans">
                <div class="flex items-center justify-end gap-2">
                  <router-link
                    :to="`/super-admin/tenants/${t.id}`"
                    class="min-h-11 px-3 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-400 hover:to-indigo-500 text-white rounded-lg text-xs font-bold transition font-tajawal cursor-pointer shadow-sm flex items-center gap-1.5 active:scale-95"
                  >
                    <Search class="w-3.5 h-3.5" />
                    <span>{{ $t('common.details') }}</span>
                  </router-link>

                  <button
                    type="button"
                    @click="$emit('open-status', t)"
                    class="min-h-11 min-w-11 inline-flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-theme-primary border border-slate-300 dark:border-slate-700 rounded-lg transition cursor-pointer active:scale-95"
                    :title="$t('super.edit_status_and_sub_btn')"
                    :aria-label="$t('super.edit_status_and_sub_btn')"
                  >
                    <Settings class="w-4 h-4" />
                  </button>

                  <template v-if="canManage">
                    <button
                      v-if="canRestoreFromArchive(t)"
                      type="button"
                      :disabled="isUnarchiving"
                      @click="$emit('unarchive-tenant', t)"
                      class="min-h-11 min-w-11 inline-flex items-center justify-center bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 rounded-lg transition cursor-pointer active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                      :title="$t('tenant_archive.unarchive_btn')"
                      :aria-label="$t('tenant_archive.unarchive_btn')"
                    >
                      <ArchiveRestore class="w-4 h-4" />
                    </button>
                    <button
                      v-else-if="t.status !== 'archived'"
                      type="button"
                      :disabled="isArchiving"
                      @click="$emit('archive-tenant', t)"
                      class="min-h-11 min-w-11 inline-flex items-center justify-center bg-rose-500/10 hover:bg-rose-500/20 text-rose-700 dark:text-rose-400 border border-rose-500/30 rounded-lg transition cursor-pointer active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                      :title="$t('tenant_archive.confirm_btn')"
                      :aria-label="$t('tenant_archive.confirm_btn')"
                    >
                      <Archive class="w-4 h-4" />
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Tactile Cards -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="t in tenants"
          :key="t.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2.5"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <router-link
                :to="`/super-admin/tenants/${t.id}`"
                class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1"
              >
                <span>{{ t.name }}</span>
                <span class="text-xs text-purple-400">↗</span>
              </router-link>
              <div class="text-xs text-cyan-600 dark:text-cyan-400 font-mono mt-0.5">{{ t.domain }}</div>
            </div>
            <button
              type="button"
              @click="$emit('open-status', t)"
              class="min-h-11 px-3 inline-flex items-center rounded-full text-[11px] font-bold border shrink-0"
              :class="tenantStatusBadgeClass(t.status)"
            >
              {{ tenantStatusLabel(t.status, $t) }}
            </button>
          </div>

          <div class="flex items-center justify-between text-xs pt-1">
            <span
              class="px-2 py-0.5 bg-purple-500/10 border border-purple-500/30 text-purple-600 dark:text-purple-400 rounded-full font-bold text-[10px]"
            >
              {{ t.plan_name }}
            </span>
            <span class="text-slate-500 font-mono text-[11px]">{{ t.email }}</span>
          </div>

          <div
            class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800"
          >
            <router-link
              :to="`/super-admin/tenants/${t.id}`"
              class="min-h-11 px-3 bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
            >
              <Search class="w-3.5 h-3.5" />
              <span>{{ $t('common.details') }}</span>
            </router-link>

            <button
              type="button"
              @click="$emit('open-status', t)"
              class="min-h-11 px-3 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
            >
              <Settings class="w-3.5 h-3.5" />
              <span>{{ $t('super.edit_status_and_sub_btn') }}</span>
            </button>

            <template v-if="canManage">
              <button
                v-if="canRestoreFromArchive(t)"
                type="button"
                :disabled="isUnarchiving"
                @click="$emit('unarchive-tenant', t)"
                class="min-h-11 px-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95 disabled:opacity-50"
              >
                <ArchiveRestore class="w-3.5 h-3.5" />
                <span>{{ $t('tenant_archive.unarchive_btn') }}</span>
              </button>
              <button
                v-else-if="t.status !== 'archived'"
                type="button"
                :disabled="isArchiving"
                @click="$emit('archive-tenant', t)"
                class="min-h-11 px-3 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95 disabled:opacity-50"
              >
                <Archive class="w-3.5 h-3.5" />
                <span>{{ $t('tenant_archive.confirm_btn') }}</span>
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('super.no_tenants_registered')"
      :description="$t('super.no_tenants_registered')"
      :icon="Building2"
    >
      <template #action>
        <button
          type="button"
          @click="$emit('open-create')"
          class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-black rounded-xl text-xs shadow-lg shadow-purple-500/20 cursor-pointer"
        >
          {{ $t('super.new_tenant_btn') }}
        </button>
      </template>
    </EmptyState>
  </div>
</template>

<script setup>
import { ExternalLink, Building2, Search, Settings, Archive, ArchiveRestore } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { canRestoreFromArchive, tenantStatusBadgeClass, tenantStatusLabel } from '../../helpers/tenantStatus';

defineProps({
  tenants: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  // super_admin.tenants.manage: archive / unarchive buttons are hidden without it.
  canManage: { type: Boolean, default: false },
  isArchiving: { type: Boolean, default: false },
  isUnarchiving: { type: Boolean, default: false },
});

defineEmits(['open-status', 'open-create', 'archive-tenant', 'unarchive-tenant']);
</script>
