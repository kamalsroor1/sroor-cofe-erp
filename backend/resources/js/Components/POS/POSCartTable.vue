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

    <!-- 📋 ACTIVE INVOICE ITEMS CONTAINER -->
    <div class="flex-1 overflow-y-auto my-3 custom-scrollbar rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 shadow-xs">
      
      <template v-if="cart.length > 0">
        
        <!-- 📱 Mobile Cart Items Card List (block md:hidden) -->
        <div class="block md:hidden space-y-2 p-2 divide-y divide-slate-100 dark:divide-slate-800/60">
          <div
            v-for="(item, idx) in cart"
            :key="item.id"
            class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3"
          >
            <!-- Row 1: Header (#, Name, Delete) -->
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-start gap-2 min-w-0 flex-1">
                <span class="w-6 h-6 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono font-black text-xs flex items-center justify-center shrink-0 mt-0.5">
                  {{ idx + 1 }}
                </span>
                <div class="min-w-0 flex-1">
                  <div class="font-black text-xs sm:text-sm text-slate-950 dark:text-white leading-snug break-words" dir="auto">
                    {{ item.name }}
                  </div>
                  <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 font-mono">
                    <span class="font-bold text-slate-400 bg-white dark:bg-slate-900 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800">{{ item.code || '—' }}</span>
                    <span>•</span>
                    <span class="text-slate-500 font-tajawal">{{ item.unit || 'قطعة' }}</span>
                  </div>
                </div>
              </div>

              <button
                type="button"
                @click="$emit('remove-item', idx)"
                class="min-h-[36px] min-w-[36px] p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer flex items-center justify-center shrink-0"
                :title="$t('pos.delete_item')"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <!-- Row 2: Quantity Controls + Price & Subtotal -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-800/60 gap-2">
              <!-- Qty buttons -->
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  @click="$emit('decrease-qty', idx)"
                  class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 hover:bg-rose-500/20 hover:text-rose-500 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700 text-base"
                >
                  -
                </button>
                <input
                  type="number"
                  :value="item.quantity"
                  @input="$emit('update-qty', { index: idx, value: $event.target.value })"
                  step="1"
                  min="1"
                  class="w-14 h-9 text-center font-mono font-black text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                />
                <button
                  type="button"
                  @click="$emit('increase-qty', idx)"
                  class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 hover:bg-emerald-500/20 hover:text-emerald-500 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black transition active:scale-95 cursor-pointer border border-slate-200 dark:border-slate-700 text-base"
                >
                  +
                </button>
              </div>

              <!-- Price Breakdown (Editable Selling Price) -->
              <div class="flex items-center justify-between sm:justify-end gap-2">
                <div class="flex items-center gap-1">
                  <label class="text-[10px] font-bold text-slate-500">{{ $t('pos.item_price') }}:</label>
                  <input
                    type="number"
                    :value="item.unit_price"
                    @input="$emit('update-price', { index: idx, value: $event.target.value })"
                    step="any"
                    min="0"
                    class="w-18 h-8 px-1.5 text-center font-mono font-black text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none"
                  />
                </div>
                <div class="text-xs font-black font-mono text-emerald-600 dark:text-emerald-400">
                  {{ formatMoney(item.quantity * item.unit_price) }} <span class="text-[9px] font-normal text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 💻 Desktop Cart Items Table (hidden md:table) -->
        <table class="hidden md:table w-full text-start text-xs border-collapse">
          <thead class="bg-slate-100/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-300 font-bold sticky top-0 border-b border-slate-200 dark:border-slate-700/80 z-10">
            <tr>
              <th class="p-3 text-center w-12">#</th>
              <th class="p-3 text-start">{{ $t('pos.item_and_code') }}</th>
              <th class="p-3 text-center w-36">{{ $t('pos.item_qty') }}</th>
              <th class="p-3 text-start w-32">{{ $t('pos.item_price') }}</th>
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
                <div class="font-black text-sm text-slate-950 dark:text-white leading-snug break-words" dir="auto">
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

              <!-- Unit Price (Editable input) -->
              <td class="p-3 font-mono">
                <div class="flex items-center gap-1">
                  <input
                    type="number"
                    :value="item.unit_price"
                    @input="$emit('update-price', { index: idx, value: $event.target.value })"
                    step="any"
                    min="0"
                    class="w-24 h-9 px-2 text-start font-mono font-black text-sm bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-950 dark:text-white focus:ring-2 focus:ring-theme-primary focus:border-theme-primary focus:outline-none"
                  />
                  <span class="text-[10px] font-bold text-slate-400 font-tajawal">{{ $t('common.currency') }}</span>
                </div>
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
      </template>

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

defineEmits(['increase-qty', 'decrease-qty', 'update-qty', 'update-price', 'remove-item']);
</script>
