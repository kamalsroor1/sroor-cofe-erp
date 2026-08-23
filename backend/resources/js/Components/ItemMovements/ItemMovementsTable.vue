<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="7" />
    </div>

    <!-- Data Loaded -->
    <div v-else-if="movements.length > 0">
      <!-- 1. Desktop & Tablet Table (hidden md:block) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.movement_type') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.reference_no') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('common.quantity') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.stock_before') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.stock_after') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.store_user') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(row, idx) in movements"
              :key="row.id || idx"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
              <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-300">{{ row.created_at }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">
                <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold border" :class="getMovementBadge(row.movement_type)">
                  {{ formatMovementLabel(row.movement_type) }}
                </span>
              </td>
              <td class="py-3.5 px-4 font-mono text-theme-primary font-bold">{{ row.document_number || '—' }}</td>
              <td class="py-3.5 px-4 text-end font-mono font-black" :class="isPositiveMovement(row.movement_type) ? 'text-emerald-500 dark:text-emerald-400' : 'text-rose-500'">
                {{ isPositiveMovement(row.movement_type) ? '+' : '-' }}{{ formatQty(row.quantity) }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono text-slate-500 dark:text-slate-400">
                {{ formatQty(row.stock_before) }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black text-slate-900 dark:text-white">
                {{ formatQty(row.stock_after) }}
              </td>
              <td class="py-3.5 px-4 font-tajawal text-slate-700 dark:text-slate-300">
                <div class="font-bold">{{ row.store?.name || $t('common.main_branch') }}</div>
                <div class="text-[10px] text-slate-400">{{ row.user?.name || $t('common.system') }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 2. Mobile Responsive Tactile Cards (block md:hidden) -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="(row, idx) in movements"
          :key="row.id || idx"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold border" :class="getMovementBadge(row.movement_type)">
                {{ formatMovementLabel(row.movement_type) }}
              </span>
              <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-1">{{ row.created_at }}</p>
            </div>

            <div class="text-end">
              <span class="text-sm font-black font-mono" :class="isPositiveMovement(row.movement_type) ? 'text-emerald-500' : 'text-rose-500'">
                {{ isPositiveMovement(row.movement_type) ? '+' : '-' }}{{ formatQty(row.quantity) }}
              </span>
              <p class="text-[10px] text-theme-primary font-mono font-bold">{{ row.document_number || '—' }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs font-mono">
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('inventory.stock_before') }}:</span>
              <span class="font-bold text-slate-600 dark:text-slate-300">{{ formatQty(row.stock_before) }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('inventory.stock_after') }}:</span>
              <span class="font-black text-slate-900 dark:text-white">{{ formatQty(row.stock_after) }}</span>
            </div>
            <div class="col-span-2 text-[10px] text-slate-500 font-sans pt-1 border-t border-slate-50 dark:border-slate-800/50 flex items-center justify-between">
              <span>🏬 {{ row.store?.name || $t('common.main_branch') }}</span>
              <span>👤 {{ row.user?.name || $t('common.system') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('inventory.no_movements_found')"
      :description="$t('inventory.no_movements_description')"
      icon="📦"
    />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatQty } = useFormatters();

defineProps({
  movements: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  getMovementBadge: { type: Function, required: true },
  formatMovementLabel: { type: Function, required: true },
  isPositiveMovement: { type: Function, required: true },
});
</script>
