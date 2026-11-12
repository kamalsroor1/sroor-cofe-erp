<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg space-y-4 font-tajawal"
  >
    <h2
      class="text-xs font-bold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 pb-2"
    >
      {{ $t('returns.source_lines_title') }}
    </h2>

    <div v-if="isLoading" class="space-y-2">
      <div v-for="n in 3" :key="n" class="h-12 rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
    </div>

    <div v-else-if="lines.length === 0" class="p-8 text-center text-xs font-bold text-slate-500">
      {{ $t('returns.nothing_returnable') }}
    </div>

    <div v-else class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-x-auto">
      <table class="w-full text-start text-xs border-collapse">
        <thead>
          <tr class="bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-400">
            <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
            <th class="p-3 text-center font-bold">{{ $t('returns.sold_qty') }}</th>
            <th class="p-3 text-center font-bold">{{ $t('returns.returned_qty') }}</th>
            <th class="p-3 text-center font-bold">{{ $t('returns.remaining_qty') }}</th>
            <th class="p-3 text-end font-bold">{{ $t('returns.refund_unit_price') }}</th>
            <th class="p-3 text-center font-bold w-28">{{ $t('returns.return_qty') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
          <tr v-for="line in lines" :key="line.id">
            <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">
              <div>{{ line.item_name }}</div>
              <span class="text-[10px] text-slate-500 font-mono">{{ line.item_code }}</span>
            </td>
            <td class="p-3 text-center font-mono">{{ line.quantity }}</td>
            <td class="p-3 text-center font-mono">{{ line.returned_quantity }}</td>
            <td class="p-3 text-center font-mono font-bold">{{ line.remaining_quantity }}</td>
            <td class="p-3 text-end font-mono text-theme-primary font-bold">{{ line.unit_price }}</td>
            <td class="p-3 text-center">
              <input
                :value="line.return_quantity"
                @input="$emit('set-quantity', line, $event.target.value)"
                type="number"
                step="0.001"
                min="0"
                :max="line.remaining_quantity"
                data-testid="return-line-qty"
                class="w-24 min-h-[44px] lg:min-h-0 lg:h-8 px-2 text-center bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  lines: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
});

defineEmits(['set-quantity']);
</script>
