<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal">
    <!-- 🔄 Skeleton Loading State (Facebook-Style Shimmer) -->
    <TableSkeleton v-if="isLoading" :columns-count="9" :rows-count="5" />

    <!-- 📦 Items List Content -->
    <template v-else-if="items.length > 0">
      <!-- 1. 🖥️ Desktop / Tablet High-Density Table (hidden on mobile < md) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-950/80 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.code') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.item_name') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.category') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.cost_price') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.retail_price') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.wholesale_price') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.current_stock') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(item, idx) in items"
              :key="item.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              :class="item.is_low_stock ? 'bg-rose-500/5' : ''"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">
                {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
              </td>
              <td class="py-3.5 px-4 font-mono font-bold text-theme-primary">
                {{ item.code || '—' }}
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ item.name }}</div>
                <div v-if="item.notes" class="text-[10px] text-slate-500 font-tajawal mt-0.5 max-w-xs truncate">
                  {{ item.notes }}
                </div>
              </td>
              <td class="py-3.5 px-4 font-tajawal">
                <span v-if="item.category" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-[11px] font-bold">
                  {{ item.category }}
                </span>
                <span v-else class="text-slate-400 font-mono">—</span>
              </td>
              <td class="py-3.5 px-4 text-end font-mono text-slate-500 dark:text-slate-400">
                {{ formatMoney(item.cost_price) }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-500">
                {{ formatMoney(item.selling_price) }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono text-purple-600 dark:text-purple-400">
                {{ formatMoney(item.min_selling_price || item.selling_price) }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-bold">
                <span :class="item.current_stock <= 0 ? 'text-slate-400' : (item.is_low_stock ? 'text-rose-500' : 'text-slate-800 dark:text-slate-200')">
                  {{ formatQty(item.current_stock) }} {{ item.unit }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center font-tajawal">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-block"
                  :class="item.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-slate-200 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-500'"
                >
                  {{ item.is_active ? $t('common.active') : $t('common.inactive') }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- Quick Adjust Button -->
                  <button
                    type="button"
                    @click="$emit('adjust', item)"
                    class="p-1.5 text-slate-400 hover:text-theme-primary hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer"
                    :title="$t('inventory.adjust_stock')"
                  >
                    <Sliders class="w-4 h-4" />
                  </button>

                  <!-- Row Actions Dropdown Standard -->
                  <ActionMenu
                    :items="getItemActions(item)"
                    :title="item.name"
                    button-class="h-8 w-8 min-w-[32px] p-0"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 2. 📱 Mobile Tactile Cards Stack (hidden on desktop >= md) -->
      <div class="block md:hidden p-3 space-y-3 font-tajawal">
        <div
          v-for="item in items"
          :key="'mob-' + item.id"
          class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 space-y-3 transition-all"
          :class="item.is_low_stock ? 'border-rose-500/30 bg-rose-500/5' : ''"
        >
          <!-- Top Row: Name + Category + Status -->
          <div class="flex items-start justify-between gap-2">
            <div class="space-y-1 min-w-0">
              <div class="font-black text-sm text-slate-900 dark:text-white truncate">
                {{ item.name }}
              </div>
              <div class="flex items-center gap-2 text-xs">
                <span class="font-mono font-bold text-theme-primary text-[11px]">{{ item.code || '—' }}</span>
                <span v-if="item.category" class="px-2 py-0.5 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[10px] text-slate-600 dark:text-slate-300 font-bold">
                  {{ item.category }}
                </span>
              </div>
            </div>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold border shrink-0"
              :class="item.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500' : 'bg-slate-200 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-500'"
            >
              {{ item.is_active ? $t('common.active') : $t('common.inactive') }}
            </span>
          </div>

          <!-- Stock & Pricing Grid -->
          <div class="grid grid-cols-2 gap-2 p-2.5 bg-white dark:bg-slate-900/80 rounded-xl border border-slate-100 dark:border-slate-800 text-xs">
            <div>
              <span class="text-[10px] text-slate-400 block">{{ $t('inventory.current_stock') }}:</span>
              <span
                class="font-mono font-black text-sm"
                :class="item.current_stock <= 0 ? 'text-slate-400' : (item.is_low_stock ? 'text-rose-500' : 'text-slate-900 dark:text-white')"
              >
                {{ formatQty(item.current_stock) }} {{ item.unit }}
              </span>
              <div v-if="item.is_low_stock" class="text-[10px] text-rose-500 font-bold mt-0.5">
                🚨 {{ $t('inventory.min_stock_level') }}: {{ formatQty(item.min_stock_level) }}
              </div>
            </div>

            <div class="text-end">
              <span class="text-[10px] text-slate-400 block">{{ $t('inventory.retail_price') }}:</span>
              <span class="font-mono font-black text-sm text-emerald-500">
                {{ formatMoney(item.selling_price) }} <span class="text-[10px] font-normal font-tajawal">{{ $t('common.currency') }}</span>
              </span>
              <div class="text-[10px] text-purple-500 dark:text-purple-400 font-mono mt-0.5">
                {{ $t('inventory.wholesale_price') }}: {{ formatMoney(item.min_selling_price || item.selling_price) }}
              </div>
            </div>
          </div>

          <!-- Mobile Actions Bar (>= 44px with ActionMenu) -->
          <div class="grid grid-cols-2 gap-2 pt-1">
            <BaseButton
              type="button"
              variant="outline"
              size="sm"
              :icon="Sliders"
              :label="$t('inventory.adjust')"
              @click="$emit('adjust', item)"
            />

            <ActionMenu
              :items="getItemActions(item)"
              :title="item.name"
              button-class="w-full min-h-[40px] rounded-xl font-bold text-xs"
            />
          </div>
        </div>
      </div>

      <!-- 📄 Pagination Bar -->
      <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 font-tajawal">
        <div class="text-xs text-slate-500 dark:text-slate-400">
          {{ $t('inventory.total_results_items', { count: pagination.total }) }}
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
      :title="$t('inventory.no_items_found')"
      :description="$t('inventory.no_items_description')"
      :icon="'☕'"
    >
      <template #action>
        <BaseButton
          type="button"
          variant="gradient"
          size="md"
          :icon="Plus"
          :label="$t('inventory.add_first_item')"
          @click="$emit('create')"
        />
      </template>
    </EmptyState>
  </div>
</template>

<script setup>
import { Sliders, History, Pencil, Trash2, Plus } from 'lucide-vue-next';
import EmptyState from '../Common/EmptyState.vue';
import BaseButton from '../Common/BaseButton.vue';
import ActionMenu from '../ActionMenu.vue';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { trans } from '../../helpers/trans';

const { formatMoney, formatQty } = useFormatters();

defineProps({
  items: { type: Array, default: () => [] },
  pagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }),
  },
  isLoading: { type: Boolean, default: false },
});

const emit = defineEmits(['create', 'edit', 'adjust', 'delete', 'page-change']);

const getItemActions = (item) => [
  {
    label: trans('inventory.adjust_stock'),
    icon: Sliders,
    onClick: () => emit('adjust', item),
  },
  {
    label: trans('inventory.movements_log'),
    icon: History,
    href: `/items/${item.id}/movements`,
  },
  {
    label: trans('common.edit'),
    icon: Pencil,
    onClick: () => emit('edit', item),
  },
  {
    label: trans('common.delete'),
    icon: Trash2,
    variant: 'danger',
    onClick: () => emit('delete', item),
  },
];
</script>
