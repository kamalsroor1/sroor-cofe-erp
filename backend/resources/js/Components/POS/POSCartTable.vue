<template>
  <div class="flex flex-col h-full font-tajawal select-none">
    
    <!-- Table Card Header -->
    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
      <div class="flex items-center gap-2">
        <span class="text-lg">🧾</span>
        <h2 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('pos.current_invoice_items') }}</h2>
        <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
          {{ $t('pos.items_count_badge', { items: cart.length, qty: totalQty }) }}
        </span>
      </div>

      <div class="text-xs text-slate-500 dark:text-slate-400 font-bold hidden sm:block">
        {{ $t('pos.search_kbd_hint') }}
      </div>
    </div>

    <!-- 📋 ACTIVE INVOICE ITEMS TABLE -->
    <div class="flex-1 overflow-y-auto my-3 custom-scrollbar rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 shadow-xs">
      
      <table v-if="cart.length > 0" class="w-full text-start text-xs border-collapse">
        <thead class="bg-slate-100/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-300 font-bold sticky top-0 border-b border-slate-200 dark:border-slate-700/80 z-10">
          <tr>
            <th class="p-3 text-center w-12">#</th>
            <th class="p-3 text-start">{{ $t('pos.item_and_code') }}</th>
            <th class="p-3 text-center w-36">{{ $t('pos.item_qty') }}</th>
            <th class="p-3 text-start w-28">{{ $t('pos.item_price') }}</th>
            <th class="p-3 text-start w-32">{{ $t('pos.item_total') }}</th>
            <th class="p-3 text-center w-14">{{ $t('pos.delete_item') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/70 font-tajawal">
          <tr
            v-for="(item, idx) in cart"
            :key="item.id"
            class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
          >
            <!-- Row Number -->
            <td class="p-3 text-center font-mono font-bold text-slate-400">
              {{ idx + 1 }}
            </td>

            <!-- Item Info -->
            <td class="p-3">
              <div class="font-black text-sm text-slate-950 dark:text-white leading-snug">
                {{ item.name }}
              </div>
              <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 font-mono">
                <span class="font-bold text-slate-400">{{ item.code || '—' }}</span>
                <span>•</span>
                <span class="text-slate-500 font-tajawal">{{ item.unit || 'قطعة' }}</span>
                <span v-if="item.price_wholesale" class="text-purple-600 dark:text-purple-400 font-bold" :title="$t('pos.min_selling_price')">
                  ({{ $t('pos.min_selling_price') }}: {{ formatMoney(item.min_selling_price || item.price_wholesale) }})
                </span>
              </div>
            </td>

            <!-- Quantity Controls -->
            <td class="p-3">
              <div class="flex items-center justify-center gap-1">
                <button
                  type="button"
                  @click="$emit('decrease-qty', idx)"
                  class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-rose-500/20 hover:text-rose-500 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700 text-base"
                >
                  -
                </button>
                <input
                  type="number"
                  :value="item.quantity"
                  @input="$emit('update-qty', { index: idx, value: $event.target.value })"
                  step="1"
                  min="1"
                  class="w-16 h-9 text-center font-mono font-black text-sm bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                />
                <button
                  type="button"
                  @click="$emit('increase-qty', idx)"
                  class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-emerald-500/20 hover:text-emerald-500 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700 text-base"
                >
                  +
                </button>
              </div>
            </td>

            <!-- Unit Price -->
            <td class="p-3 font-mono font-black text-sm text-slate-800 dark:text-slate-200">
              {{ formatMoney(item.unit_price) }}
            </td>

            <!-- Line Subtotal -->
            <td class="p-3 font-mono font-black text-base text-emerald-600 dark:text-emerald-400">
              {{ formatMoney(item.quantity * item.unit_price) }}
            </td>

            <!-- Delete Action -->
            <td class="p-3 text-center">
              <button
                type="button"
                @click="$emit('remove-item', idx)"
                class="min-h-[40px] min-w-[40px] p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition cursor-pointer flex items-center justify-center mx-auto"
                :title="$t('pos.delete_item')"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Empty Cart State -->
      <div v-else class="h-full min-h-[260px] flex flex-col items-center justify-center p-8 text-center text-slate-400">
        <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl mb-3 shadow-inner">
          🛒
        </div>
        <h3 class="text-base font-black text-slate-800 dark:text-slate-200">{{ $t('pos.empty_cart_title') }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm">
          {{ $t('pos.empty_cart_desc') }}
        </p>
      </div>

    </div>

  </div>
</template>

<script setup>
import { Trash2 } from 'lucide-vue-next';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  cart: { type: Array, default: () => [] },
  totalQty: { type: Number, default: 0 },
});

defineEmits(['increase-qty', 'decrease-qty', 'update-qty', 'remove-item']);
</script>
