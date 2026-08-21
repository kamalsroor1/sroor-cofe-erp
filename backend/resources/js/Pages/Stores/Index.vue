<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import AppModal from '@/Components/Common/AppModal.vue';
import { trans } from '@/helpers/trans';

const props = defineProps({
    stores: { type: Array, default: () => [] },
    all_users: { type: Array, default: () => [] },
});

// Create / Edit Store Modal
const showStoreModal = ref(false);
const editingStore = ref(null);

const storeForm = useForm({
    name: '',
    code: '',
    type: 'retail_shop',
    address: '',
    phone: '',
    is_active: true,
    is_main: false,
});

const openCreateModal = () => {
    editingStore.value = null;
    storeForm.reset();
    storeForm.clearErrors();
    storeForm.type = 'retail_shop';
    storeForm.is_active = true;
    storeForm.is_main = false;
    showStoreModal.value = true;
};

const openEditModal = (s) => {
    editingStore.value = s;
    storeForm.clearErrors();
    storeForm.name = s.name;
    storeForm.code = s.code;
    storeForm.type = s.type;
    storeForm.address = s.address || '';
    storeForm.phone = s.phone || '';
    storeForm.is_active = s.is_active;
    storeForm.is_main = s.is_main;
    showStoreModal.value = true;
};

const saveStore = () => {
    if (editingStore.value) {
        storeForm.put(`/stores/${editingStore.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showStoreModal.value = false;
            }
        });
    } else {
        storeForm.post('/stores', {
            preserveScroll: true,
            onSuccess: () => {
                showStoreModal.value = false;
            }
        });
    }
};

const toggleActive = (s) => {
    if (s.is_main && s.is_active) {
        alert(trans('inventory.cannot_disable_main_store') || 'لا يمكن تعطيل الفرع والمخزن الرئيسي للمنشأة');
        return;
    }
    router.post(`/stores/${s.id}/toggle-active`, {}, {
        preserveScroll: true,
    });
};

const deleteStore = (s) => {
    if (!s.can_be_deleted) {
        alert((trans('inventory.cannot_delete_store') || 'لا يمكن حذف الفرع/المخزن') + ':\n- ' + s.deletion_blockers.join('\n- '));
        return;
    }
    if (confirm(trans('common.confirm_delete') || `هل أنت متأكد من حذف (${s.name})؟`)) {
        router.delete(`/stores/${s.id}`, {
            preserveScroll: true,
        });
    }
};

// Staff Assignment Modal
const showUserModal = ref(false);
const targetStore = ref(null);
const userAssignmentForm = useForm({
    user_ids: [],
});

const openUserAssignmentModal = (s) => {
    targetStore.value = s;
    userAssignmentForm.user_ids = [...(s.assigned_user_ids || [])];
    showUserModal.value = true;
};

const saveUserAssignment = () => {
    if (!targetStore.value) return;
    userAssignmentForm.post(`/stores/${targetStore.value.id}/assign-users`, {
        preserveScroll: true,
        onSuccess: () => {
            showUserModal.value = false;
        }
    });
};
</script>

<template>
    <Head :title="$t('inventory.stores_title')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('inventory.stores_title')"
                :subtitle="$t('inventory.stores_subtitle')"
                icon="🏬"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                        <Link
                            href="/store-stocks"
                            class="flex-1 sm:flex-none h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center justify-center gap-1.5 transition active:scale-95 shadow-xs"
                        >
                            <span>📊</span>
                            <span>{{ $t('inventory.store_stocks') }}</span>
                        </Link>

                        <button
                            type="button"
                            class="flex-1 sm:flex-none h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-primary"
                            @click="openCreateModal"
                        >
                            <span class="text-base font-black">+</span>
                            <span>{{ $t('inventory.add_new_store') }}</span>
                        </button>
                    </div>
                </template>
            </PageHeader>

            <!-- Stores Cards Grid -->
            <div v-if="stores.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                <div
                    v-for="s in stores"
                    :key="s.id"
                    class="bg-white dark:bg-slate-900 border rounded-3xl p-4 sm:p-5 shadow-xs space-y-4 transition hover:border-theme-primary flex flex-col justify-between"
                    :class="s.is_main ? 'border-theme-primary dark:bg-gradient-to-br dark:from-slate-900 dark:to-slate-950' : 'border-slate-200 dark:border-slate-800'"
                >
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xl shrink-0">
                                    <span v-if="s.type === 'wholesale_van' || s.type === 'van'">🚚</span>
                                    <span v-else-if="s.type === 'main_warehouse' || s.type === 'warehouse'">🏭</span>
                                    <span v-else>🏬</span>
                                </div>
                                <div>
                                    <h3 class="font-black text-slate-900 dark:text-white text-sm flex items-center gap-2">
                                        <span>{{ s.name }}</span>
                                        <span v-if="s.is_main" class="px-2 py-0.5 rounded-md tab-theme-active text-[10px] font-black">{{ $t('inventory.store_type_main') }} 👑</span>
                                    </h3>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-slate-400 dark:text-slate-500 font-mono font-bold">{{ s.code }}</span>
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal">
                                            ({{ s.type === 'wholesale_van' || s.type === 'van' ? 'عربية توزيع جملة' : (s.type === 'main_warehouse' || s.type === 'warehouse' ? $t('inventory.store_type_main') : $t('inventory.store_type_branch')) }})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="px-2.5 py-1 rounded-full text-[10px] font-bold transition cursor-pointer active:scale-95"
                                :class="s.is_active ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/30' : 'bg-rose-500/20 text-rose-600 dark:text-rose-400 hover:bg-rose-500/30'"
                                :title="s.is_active ? $t('common.active') : $t('common.inactive')"
                                @click="toggleActive(s)"
                            >
                                {{ s.is_active ? '🟢 ' + $t('common.active') : '⚪ ' + $t('common.inactive') }}
                            </button>
                        </div>

                        <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1.5 font-tajawal pt-2 border-t border-slate-100 dark:border-slate-800/80">
                            <div v-if="s.address" class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300">
                                <span>📍</span>
                                <span>{{ s.address }}</span>
                            </div>
                            <div v-if="s.phone" class="flex items-center gap-1.5 font-mono text-slate-700 dark:text-slate-300">
                                <span>📱</span>
                                <span>{{ s.phone }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <span>📦 {{ $t('inventory.total_items_count') }}:</span>
                                <strong class="text-slate-900 dark:text-white font-mono text-xs">{{ s.stocks_count }} {{ $t('inventory.item_unit') }}</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>🧾 {{ $t('dashboard.invoices_today') || 'الفواتير' }}:</span>
                                <span class="text-slate-700 dark:text-slate-300 font-mono">{{ s.invoices_count || 0 }}</span>
                            </div>
                        </div>

                        <!-- Assigned Staff -->
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 space-y-1.5">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 dark:text-slate-400 font-bold">{{ $t('users.title') || 'الموظفون المعينون' }}:</span>
                                <button
                                    type="button"
                                    class="text-theme-primary hover:underline text-[10px] font-black cursor-pointer"
                                    @click="openUserAssignmentModal(s)"
                                >
                                    + {{ $t('common.edit') || 'إدارة الموظفين' }}
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="u in s.assigned_users"
                                    :key="u.id"
                                    class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold border border-slate-200 dark:border-transparent"
                                >
                                    👤 {{ u.name }}
                                </span>
                                <span v-if="!s.assigned_users || s.assigned_users.length === 0" class="text-[10px] text-slate-400 dark:text-slate-500 italic">
                                    {{ $t('common.no_records') || 'لا يوجد موظفون مخصصون' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-3 flex items-center justify-between gap-2 border-t border-slate-100 dark:border-slate-800">
                        <Link
                            :href="`/store-stocks?store_id=${s.id}`"
                            class="h-10 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition flex items-center gap-1.5 border border-slate-200 dark:border-transparent active:scale-95 shadow-xs"
                        >
                            <span>📦</span>
                            <span>{{ $t('inventory.store_stocks') }}</span>
                        </Link>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition flex items-center justify-center border border-slate-200 dark:border-transparent active:scale-95 cursor-pointer shadow-xs"
                                :title="$t('common.edit')"
                                @click="openEditModal(s)"
                            >
                                ✏️
                            </button>
                            <button
                                v-if="!s.is_main"
                                type="button"
                                class="w-10 h-10 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 text-rose-600 dark:text-rose-400 text-xs font-bold transition flex items-center justify-center border border-rose-200 dark:border-rose-900 active:scale-95 cursor-pointer shadow-xs"
                                :title="$t('common.delete')"
                                @click="deleteStore(s)"
                            >
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <EmptyState
                v-else
                :title="$t('inventory.no_stores_found')"
                :action-text="$t('inventory.add_new_store')"
                icon="🏬"
                @action="openCreateModal"
            />
        </div>

        <!-- Create / Edit Store Modal -->
        <AppModal
            :show="showStoreModal"
            :title="editingStore ? $t('inventory.edit_store') : $t('inventory.add_new_store')"
            :icon="editingStore ? '✏️' : '🏬'"
            max-width="lg"
            @close="showStoreModal = false"
        >
            <form id="storeForm" @submit.prevent="saveStore" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.store_name') }} *</label>
                        <input
                            v-model="storeForm.name"
                            type="text"
                            required
                            placeholder="مثال: فرع وسط البلد"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.store_code') }} *</label>
                        <input
                            v-model="storeForm.code"
                            type="text"
                            required
                            placeholder="STR-001"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono uppercase placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.store_type') }}</label>
                    <select
                        v-model="storeForm.type"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-theme-primary focus:outline-none shadow-inner"
                    >
                        <option value="retail_shop">🏬 {{ $t('inventory.store_type_branch') }}</option>
                        <option value="main_warehouse">🏭 {{ $t('inventory.store_type_main') }}</option>
                        <option value="warehouse">🏢 مخزن تخزين وسيط</option>
                        <option value="wholesale_van">🚚 عربية / مندوب توزيع جملة</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.store_address') }}</label>
                        <input
                            v-model="storeForm.address"
                            type="text"
                            placeholder="العنوان التفصيلي"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('contacts.phone') }}</label>
                        <input
                            v-model="storeForm.phone"
                            type="text"
                            placeholder="010xxxxxxxx"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white font-mono placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>
                </div>

                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" v-model="storeForm.is_active" class="w-4 h-4 rounded accent-theme-primary">
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $t('common.active') }}</span>
                    </label>

                    <label v-if="!editingStore?.is_main" class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" v-model="storeForm.is_main" class="w-4 h-4 rounded accent-theme-primary">
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $t('inventory.store_type_main') }} (افتراضي)</span>
                    </label>
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        class="h-11 px-5 rounded-2xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                        @click="showStoreModal = false"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        form="storeForm"
                        :disabled="storeForm.processing"
                        class="h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
                    >
                        {{ storeForm.processing ? $t('common.save') + '...' : $t('common.save') }}
                    </button>
                </div>
            </template>
        </AppModal>

        <!-- Assign Users Modal -->
        <AppModal
            :show="showUserModal"
            :title="`${$t('inventory.edit_store')}: ${targetStore?.name}`"
            icon="👥"
            max-width="md"
            @close="showUserModal = false"
        >
            <form id="userAssignmentForm" @submit.prevent="saveUserAssignment" class="space-y-3">
                <div
                    v-for="u in all_users"
                    :key="u.id"
                    class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 hover:border-theme-primary transition cursor-pointer"
                    @click="userAssignmentForm.user_ids.includes(u.id) ? userAssignmentForm.user_ids = userAssignmentForm.user_ids.filter(id => id !== u.id) : userAssignmentForm.user_ids.push(u.id)"
                >
                    <div class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            :value="u.id"
                            v-model="userAssignmentForm.user_ids"
                            class="w-4 h-4 rounded accent-theme-primary pointer-events-none"
                        >
                        <div>
                            <div class="font-black text-xs text-slate-900 dark:text-white">{{ u.name }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ u.email || u.phone }}</div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold">
                        {{ u.role_label || u.role }}
                    </span>
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        class="h-11 px-5 rounded-2xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                        @click="showUserModal = false"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        form="userAssignmentForm"
                        :disabled="userAssignmentForm.processing"
                        class="h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
                    >
                        {{ userAssignmentForm.processing ? $t('common.save') + '...' : $t('common.save') }}
                    </button>
                </div>
            </template>
        </AppModal>
    </AppLayout>
</template>