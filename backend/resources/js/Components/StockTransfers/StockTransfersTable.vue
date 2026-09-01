<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xl font-tajawal">
    <!-- 🔄 Skeleton Loading State (Facebook-Style Shimmer) -->
    <TableSkeleton v-if="isLoading" :columns-count="7" :rows-count="5" />

    <!-- 🚚 Content State -->
    <template v-else-if="transfers.length > 0">
      <!-- 1. 🖥️ Desktop / Tablet High-Density Table (>= 768px) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-950/80 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.transfer_number') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.from_store') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.to_store') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.transfer_items') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(trf, idx) in transfers"
              :key="trf.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              :class="trf.is_cancelled ? 'opacity-60 bg-rose-500/5' : ''"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">
                {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
              </td>

              <!-- Transfer Number -->
              <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">
                <button
                  type="button"
                  @click="$emit('preview', trf)"
                  class="hover:underline cursor-pointer font-bold"
                >
                  {{ trf.transfer_number }}
                </button>
              </td>

              <!-- From Store -->
              <td class="py-3.5 px-4 font-bold text-slate-700 dark:text-slate-300 font-tajawal">
                {{ trf.from_store_name }}
              </td>

              <!-- To Store -->
              <td class="py-3.5 px-4 font-bold text-emerald-600 dark:text-emerald-400 font-tajawal">
                {{ trf.to_store_name }}
              </td>

              <!-- Date -->
              <td class="py-3.5 px-4 font-mono text-slate-500 dark:text-slate-400 text-[11px] whitespace-nowrap">
                {{ trf.transfer_date }}
              </td>

              <!-- Items Count -->
              <td class="py-3.5 px-4 text-center font-mono font-bold text-cyan-600 dark:text-cyan-400">
                <span class="px-2 py-0.5 rounded-lg bg-cyan-500/10 border border-cyan-500/20 text-cyan-600 dark:text-cyan-400 text-xs">
                  {{ trf.items_count }} {{ $t('inventory.item_unit') }}
                </span>
              </td>

              <!-- Status Badge -->
              <td class="py-3.5 px-4 text-center font-tajawal">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-block"
                  :class="!trf.is_cancelled ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
                >
                  {{ !trf.is_cancelled ? $t('inventory.transfer_status_done') : $t('inventory.transfer_status_cancelled') }}
                </span>
              </td>

              <!-- Actions Dropdown Standard -->
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    type="button"
                    @click="$emit('preview', trf)"
                    class="p-1.5 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-xl transition cursor-pointer"
                    :title="$t('inventory.view_transfer_details_hint')"
                  >
                    <Eye class="w-4 h-4" />
                  </button>

                  <ActionMenu
                    :items="getTransferActions(trf)"
                    :title="$t('inventory.transfer_details_modal_title', { number: trf.transfer_number })"
                    button-class="h-8 w-8 min-w-[32px] p-0"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 2. 📱 Mobile Tactile Cards Stack (< 768px) -->
      <div class="block md:hidden p-3 space-y-3 font-tajawal">
        <div
          v-for="trf in transfers"
          :key="'mob-' + trf.id"
          class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 space-y-3 transition-all"
          :class="trf.is_cancelled ? 'opacity-60 bg-rose-500/5' : ''"
        >
          <!-- Top Row: Transfer Number + Status -->
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-theme-primary/10 text-theme-primary text-sm font-black">
                🚚
              </span>
              <span class="font-mono font-black text-theme-primary text-xs">{{ trf.transfer_number }}</span>
            </div>
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border shrink-0"
              :class="!trf.is_cancelled ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'"
            >
              {{ !trf.is_cancelled ? $t('inventory.transfer_status_done') : $t('inventory.transfer_status_cancelled') }}
            </span>
          </div>

          <!-- Transfer Route Grid -->
          <div class="grid grid-cols-2 gap-2 p-2.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 text-xs">
            <div>
              <span class="text-[10px] text-slate-400 block">{{ $t('inventory.from_store') }}:</span>
              <span class="font-bold text-slate-800 dark:text-slate-200">{{ trf.from_store_name }}</span>
            </div>
            <div class="text-end">
              <span class="text-[10px] text-slate-400 block">{{ $t('inventory.to_store') }}:</span>
              <span class="font-bold text-emerald-500">{{ trf.to_store_name }}</span>
            </div>
          </div>

          <!-- Footer Info: Date + Items -->
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span class="font-mono text-[11px]">{{ trf.transfer_date }}</span>
            <span class="font-mono font-bold text-cyan-600 dark:text-cyan-400">
              {{ trf.items_count }} {{ $t('inventory.item_unit') }}
            </span>
          </div>

          <!-- Mobile Actions Bar (>= 44px with ActionMenu) -->
          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="$emit('preview', trf)"
              class="min-h-[44px] px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 rounded-xl text-xs font-bold text-cyan-600 dark:text-cyan-400 flex items-center justify-center gap-1.5 transition active:scale-95 select-none cursor-pointer"
            >
              <Eye class="w-4 h-4" />
              <span>{{ $t('inventory.view_transfer_details') }}</span>
            </button>

            <ActionMenu
              :items="getTransferActions(trf)"
              :title="$t('inventory.transfer_details_modal_title', { number: trf.transfer_number })"
              button-class="w-full min-h-[44px] rounded-xl font-bold text-xs"
            />
          </div>
        </div>
      </div>

      <!-- 📄 Pagination Bar -->
      <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 font-tajawal">
        <div class="text-xs text-slate-500 dark:text-slate-400">
          {{ $t('inventory.total_results_transfers', { count: pagination.total }) }}
        </div>
        <div class="flex items-center gap-1.5">
          <BaseButton
            type="button"
            variant="outline"
            size="sm"
            :disabled="pagination.current_page <= 1"
            :label="$t('common.previous')"
            @click="$emit('page-change', pagination.current_page - 1)"
          />
          <span class="px-3 py-1.5 text-xs font-mono text-slate-700 dark:text-slate-300 font-bold bg-slate-100 dark:bg-slate-800 rounded-xl">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <BaseButton
            type="button"
            variant="outline"
            size="sm"
            :disabled="pagination.current_page >= pagination.last_page"
            :label="$t('common.next')"
            @click="$emit('page-change', pagination.current_page + 1)"
          />
        </div>
      </div>
    </template>

    <!-- 🚫 Empty State -->
    <EmptyState
      v-else
      :title="$t('inventory.no_transfers_found')"
      :description="$t('inventory.no_movements_description')"
      :icon="'🚚'"
    >
      <template #action>
        <BaseButton
          type="button"
          variant="gradient"
          size="md"
          :icon="Plus"
          :label="$t('inventory.create_first_transfer')"
          :to="'/stock-transfers/create'"
        />
      </template>
    </EmptyState>
  </div>
</template>

<script setup>
import { Eye, Ban, Plus } from 'lucide-vue-next';
import EmptyState from '../Common/EmptyState.vue';
import BaseButton from '../Common/BaseButton.vue';
import ActionMenu from '../ActionMenu.vue';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import { trans } from '../../helpers/trans';

defineProps({
  transfers: { type: Array, default: () => [] },
  pagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }),
  },
  isLoading: { type: Boolean, default: false },
});

const emit = defineEmits(['preview', 'cancel', 'page-change']);

const getTransferActions = (trf) => [
  {
    label: trans('inventory.view_transfer_details'),
    icon: Eye,
    onClick: () => emit('preview', trf),
  },
  {
    label: trans('inventory.cancel_transfer'),
    icon: Ban,
    variant: 'danger',
    show: !trf.is_cancelled,
    onClick: () => emit('cancel', trf),
  },
];
</script>
