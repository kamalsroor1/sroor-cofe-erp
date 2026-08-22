<script setup>
import AppModal from '@/Components/Common/AppModal.vue';
import { Package } from 'lucide-vue-next';

defineProps({
    show: { type: Boolean, required: true },
    editingItem: { type: Object, default: null },
    form: { type: Object, required: true },
});

const emit = defineEmits(['close', 'submit']);
</script>

<template>
    <AppModal
        :show="show"
        :title="editingItem ? $t('inventory.edit_item') : $t('inventory.add_new_item')"
        :icon="Package"
        max-width="lg"
        @close="$emit('close')"
    >
        <form id="item-form" @submit.prevent="$emit('submit')" class="space-y-4">
            <!-- Name -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.item_name') }} *</label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    :placeholder="$t('inventory.item_name')"
                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                >
                <p v-if="form.errors?.name" class="text-rose-400 text-[10px]">{{ form.errors.name }}</p>
            </div>

            <!-- Code & Category -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.item_code') }} / {{ $t('inventory.barcode') }}</label>
                    <input
                        v-model="form.code"
                        type="text"
                        :placeholder="$t('inventory.barcode_placeholder')"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono focus:border-amber-500 focus:outline-none shadow-inner"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.category') }}</label>
                    <input
                        v-model="form.category"
                        type="text"
                        :placeholder="$t('inventory.category_placeholder')"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                    >
                </div>
            </div>

            <!-- Unit, Cost Price, Selling Price -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.unit') }} *</label>
                    <select
                        v-model="form.unit"
                        class="w-full h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner font-bold"
                    >
                        <option value="كجم">{{ $t('inventory.unit_weight_short') }} (كجم)</option>
                        <option value="جرام">{{ $t('inventory.unit_gram') }}</option>
                        <option value="قطعة">{{ $t('inventory.unit_piece_short') }}</option>
                        <option value="شيكارة">{{ $t('inventory.unit_bag_box') }}</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.purchase_price') }} *</label>
                    <input
                        v-model="form.cost_price"
                        type="number"
                        step="0.001"
                        required
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono focus:border-amber-500 focus:outline-none shadow-inner"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.retail_price') }} *</label>
                    <input
                        v-model="form.selling_price"
                        type="number"
                        step="0.001"
                        required
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono focus:border-amber-500 focus:outline-none shadow-inner"
                    >
                </div>
            </div>

            <!-- Min Stock Level -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.min_stock_level') }}</label>
                <input
                    v-model="form.min_stock_level"
                    type="number"
                    step="0.001"
                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono focus:border-amber-500 focus:outline-none shadow-inner"
                >
            </div>

            <!-- Notes -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('common.notes') }}</label>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    class="w-full p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                ></textarea>
            </div>
        </form>

        <!-- Footer Slot -->
        <template #footer>
            <button
                type="button"
                class="h-11 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                @click="$emit('close')"
            >
                {{ $t('common.cancel') }}
            </button>
            <button
                type="submit"
                form="item-form"
                :disabled="form.processing"
                class="h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
            >
                {{ form.processing ? '...' : (editingItem ? $t('common.save') : $t('inventory.add_new_item')) }}
            </button>
        </template>
    </AppModal>
</template>
