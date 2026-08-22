<script setup>
import { ref } from 'vue';
import { customerService } from '@/Services/customerService';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const form = ref({ name: '', phone: '', price_tier: 'retail', address: '' });
const isSaving = ref(false);
const errorMessage = ref('');

const saveCustomer = async () => {
    if (!form.value.name) return;
    isSaving.value = true;
    errorMessage.value = '';

    try {
        const res = await customerService.quickCreate(form.value);
        if (res.status === 'success') {
            emit('created', res.customer);
            form.value = { name: '', phone: '', price_tier: 'retail', address: '' };
        }
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'حدث خطأ أثناء حفظ بيانات العميل';
    } finally {
        isSaving.value = false;
    }
};

const handleSave = saveCustomer;
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-zoom">
            <div
                v-if="show"
                @click="emit('close')"
                class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-tajawal select-none"
            >
                <div @click.stop class="w-full max-w-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">{{ $t('pos.add_new_customer') }}</h3>
                        <button
                            @click="emit('close')"
                            type="button"
                            class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center justify-center text-sm font-bold transition active:scale-90 cursor-pointer shadow-xs shrink-0"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="space-y-1">
                            <label class="block font-bold text-slate-700 dark:text-slate-300">{{ $t('pos.customer_name') }}:</label>
                            <input
                                v-model="form.name"
                                type="text"
                                :placeholder="$t('contacts.name')"
                                class="w-full h-11 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-theme-primary shadow-inner"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-700 dark:text-slate-300">{{ $t('pos.phone') }}:</label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                inputmode="tel"
                                :placeholder="$t('contacts.phone')"
                                class="w-full h-11 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-theme-primary shadow-inner font-mono"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-slate-700 dark:text-slate-300">{{ $t('pos.pricing_tier') }}:</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="form.price_tier = 'retail'"
                                    class="p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer font-bold"
                                    :class="form.price_tier === 'retail' ? 'bg-amber-500/15 border-amber-500 text-amber-600 dark:text-amber-400' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'"
                                >
                                    {{ $t('customers.retail') }}
                                </button>
                                <button
                                    type="button"
                                    @click="form.price_tier = 'wholesale'"
                                    class="p-2.5 rounded-xl border text-center transition active:scale-95 cursor-pointer font-bold"
                                    :class="form.price_tier === 'wholesale' ? 'bg-indigo-500/15 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'"
                                >
                                    {{ $t('customers.wholesale') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        @click="handleSave"
                        type="button"
                        class="w-full h-12 rounded-2xl btn-primary-theme font-black text-xs transition transform active:scale-95 cursor-pointer shadow-theme-primary flex items-center justify-center gap-2"
                    >
                        <span>💾</span>
                        <span>{{ $t('pos.save_and_select_customer') }}</span>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
