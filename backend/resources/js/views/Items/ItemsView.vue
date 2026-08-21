<template>
  <SpaLayout>
    <div class="space-y-6 max-w-7xl mx-auto">
      <!-- Page Header -->
      <PageHeader
        :title="$t('inventory.items_title')"
        :subtitle="$t('inventory.items_subtitle')"
        :icon="'☕'"
      >
        <template #actions>
          <button
            type="button"
            @click="openCreateModal"
            class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-xs font-black transition-all flex items-center gap-2 font-tajawal shadow-lg shadow-amber-500/20 cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>{{ $t('inventory.add_item') }}</span>
          </button>
        </template>
      </PageHeader>

      <!-- Summary Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Stock Valuation -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('inventory.total_stock_value') }}</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <TrendingUp class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-emerald-400 font-mono">
            {{ formatMoney(metrics.total_stock_value || 0) }} <span class="text-xs text-slate-400">ج.م</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            إجمالي التقييم المالي للمخزون الفعلي الحالي
          </div>
        </div>

        <!-- Low Stock Count -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('inventory.low_stock_count') }}</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
              <AlertTriangle class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-rose-400 font-mono">
            {{ metrics.low_stock_count || 0 }} <span class="text-xs text-slate-400">صنف</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            أصناف وصلت للحد الأدنى وبحاجة لإعادة الطلب
          </div>
        </div>

        <!-- Total Items Count -->
        <div class="p-5 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-lg space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 font-tajawal">{{ $t('inventory.total_items_count') }}</span>
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
              <Package class="w-4 h-4" />
            </div>
          </div>
          <div class="text-2xl font-black text-white font-mono">
            {{ metrics.total_items || 0 }} <span class="text-xs text-slate-400">صنف</span>
          </div>
          <div class="text-[11px] text-slate-500 font-tajawal">
            إجمالي بطاقات الأصناف المسجلة بالنظام
          </div>
        </div>
      </div>

      <!-- Filters & Search Bar -->
      <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            class="w-full h-10 pr-9 pl-4 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
            :placeholder="$t('inventory.search_item_placeholder')"
          >
          <Search class="w-4 h-4 text-slate-500 absolute right-3 top-3 pointer-events-none" />
        </div>

        <!-- Category Dropdown -->
        <div class="w-full md:w-48">
          <select
            v-model="selectedCategory"
            @change="fetchItems(1)"
            class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-tajawal"
          >
            <option value="all">{{ $t('inventory.all_categories') }}</option>
            <option v-for="cat in categories" :key="cat" :value="cat">
              {{ cat }}
            </option>
          </select>
        </div>

        <!-- Stock Status Filter Pills -->
        <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800 overflow-x-auto">
          <button
            type="button"
            @click="setStockStatus('all')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="stockStatus === 'all' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200'"
          >
            {{ $t('common.all') }}
          </button>

          <button
            type="button"
            @click="setStockStatus('low')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="stockStatus === 'low' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-400 hover:text-slate-200'"
          >
            🚨 {{ $t('inventory.low_stock_only') }}
          </button>

          <button
            type="button"
            @click="setStockStatus('out')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="stockStatus === 'out' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'text-slate-400 hover:text-slate-200'"
          >
            ❌ {{ $t('inventory.out_of_stock_only') }}
          </button>

          <button
            type="button"
            @click="setStockStatus('in_stock')"
            class="px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
            :class="stockStatus === 'in_stock' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-slate-200'"
          >
            ✅ {{ $t('inventory.available_only') }}
          </button>
        </div>
      </div>

      <!-- Items Table -->
      <div class="bg-slate-950/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <!-- Loading Spinner -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
          <p class="text-xs text-slate-400 font-bold font-tajawal">{{ $t('common.loading') }}</p>
        </div>

        <div v-else-if="items.length > 0" class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-slate-900/90 text-slate-400 font-tajawal border-b border-slate-800">
                <th class="py-3 px-4 text-start font-bold">#</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.code') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.item_name') }}</th>
                <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.category') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.cost_price') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.selling_price') }}</th>
                <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.current_stock') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
                <th class="py-3 px-4 text-center font-bold">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
              <tr
                v-for="(item, idx) in items"
                :key="item.id"
                class="hover:bg-slate-900/50 transition-colors"
                :class="item.is_low_stock ? 'bg-rose-500/5' : ''"
              >
                <td class="py-3.5 px-4 font-mono text-slate-500">
                  {{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-amber-400">
                  {{ item.code || '—' }}
                </td>
                <td class="py-3.5 px-4">
                  <div class="font-bold text-white font-tajawal text-sm">{{ item.name }}</div>
                  <div v-if="item.notes" class="text-[10px] text-slate-500 font-tajawal mt-0.5 max-w-xs truncate">
                    {{ item.notes }}
                  </div>
                </td>
                <td class="py-3.5 px-4 font-tajawal text-slate-300">
                  <span v-if="item.category" class="px-2 py-0.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 text-[11px]">
                    {{ item.category }}
                  </span>
                  <span v-else class="text-slate-500">—</span>
                </td>
                <td class="py-3.5 px-4 text-end font-mono text-slate-400">
                  {{ formatMoney(item.cost_price) }} <span class="text-[10px]">ج.م</span>
                </td>
                <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-400">
                  {{ formatMoney(item.selling_price) }} <span class="text-[10px]">ج.م</span>
                </td>
                <td class="py-3.5 px-4 text-end">
                  <div
                    class="font-mono font-black text-sm"
                    :class="item.current_stock <= 0 ? 'text-slate-500' : (item.is_low_stock ? 'text-rose-400' : 'text-white')"
                  >
                    {{ formatQty(item.current_stock) }} <span class="text-[10px] font-normal text-slate-400 font-tajawal">{{ item.unit }}</span>
                  </div>
                  <div v-if="item.is_low_stock" class="text-[10px] text-rose-400 font-tajawal font-bold mt-0.5 flex items-center justify-end gap-1">
                    <AlertTriangle class="w-3 h-3" />
                    <span>حد أدنى ({{ formatQty(item.min_stock_level) }})</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold font-tajawal border"
                    :class="item.is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-slate-800 border-slate-700 text-slate-500'"
                  >
                    {{ item.is_active ? $t('common.active') : $t('common.inactive') }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Stock Adjustment Button -->
                    <button
                      type="button"
                      @click="openAdjustModal(item)"
                      class="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-xl text-xs font-bold transition-all flex items-center gap-1 font-tajawal cursor-pointer"
                      :title="$t('inventory.adjust_stock')"
                    >
                      <Sliders class="w-3.5 h-3.5" />
                      <span>{{ $t('inventory.adjust') }}</span>
                    </button>

                    <!-- Movements Button -->
                    <router-link
                      :to="`/items/${item.id}/movements`"
                      class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-900 rounded-xl transition-all"
                      :title="$t('inventory.movements_log')"
                    >
                      <History class="w-4 h-4" />
                    </router-link>

                    <!-- Edit Button -->
                    <button
                      type="button"
                      @click="openEditModal(item)"
                      class="p-2 text-slate-400 hover:text-cyan-400 hover:bg-slate-900 rounded-xl transition-all cursor-pointer"
                      :title="$t('common.edit')"
                    >
                      <Pencil class="w-4 h-4" />
                    </button>

                    <!-- Delete Button -->
                    <button
                      type="button"
                      @click="deleteItem(item)"
                      class="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer"
                      :title="$t('common.delete')"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <EmptyState
          v-else
          :title="$t('inventory.no_items_found')"
          :description="$t('inventory.no_items_description')"
          :icon="'☕'"
        >
          <template #action>
            <button
              type="button"
              @click="openCreateModal"
              class="px-5 py-2.5 bg-amber-500 text-slate-950 rounded-xl text-xs font-black font-tajawal shadow-lg shadow-amber-500/20 cursor-pointer"
            >
              {{ $t('inventory.add_first_item') }}
            </button>
          </template>
        </EmptyState>

        <!-- Pagination Bar -->
        <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-800 flex items-center justify-between">
          <div class="text-xs text-slate-400 font-tajawal">
            إجمالي النتائج: <span class="font-mono text-amber-400">{{ pagination.total }}</span> صنف
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="fetchItems(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              السابق
            </button>
            <span class="px-3 py-1.5 text-xs font-mono text-slate-300 font-bold">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchItems(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-slate-300 disabled:opacity-40 cursor-pointer font-tajawal"
            >
              التالي
            </button>
          </div>
        </div>
      </div>

      <!-- Add / Edit Item Modal -->
      <AppModal
        :show="showItemModal"
        :title="editingItem ? $t('inventory.edit_item') : $t('inventory.add_item')"
        @close="showItemModal = false"
      >
        <form @submit.prevent="saveItem" class="space-y-4 font-tajawal">
          <!-- Name & Code Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('inventory.item_name') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                :placeholder="$t('inventory.item_name_placeholder')"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('inventory.code') }} (باركود)
              </label>
              <input
                v-model="form.code"
                type="text"
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
                placeholder="توليد تلقائي إذا تُرك فارغاً"
              >
            </div>
          </div>

          <!-- Category & Unit Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('inventory.category') }}
              </label>
              <input
                v-model="form.category"
                type="text"
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                placeholder="مثال: بن حبوب، بهارات، مشروبات"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('inventory.unit') }} <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.unit"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              >
                <option value="كجم">كيلوجرام (كجم)</option>
                <option value="جرام">جرام (جم)</option>
                <option value="قطعة">قطعة / حبة</option>
                <option value="شيكارة">شيكارة / جوال</option>
                <option value="علبة">علبة / باكت</option>
                <option value="كرتونة">كرتونة</option>
                <option value="لتر">لتر</option>
              </select>
            </div>
          </div>

          <!-- Cost Price, Selling Price & Min Stock Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('inventory.cost_price') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.cost_price"
                type="number"
                step="0.001"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
                placeholder="0.00"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('inventory.selling_price') }} <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.selling_price"
                type="number"
                step="0.001"
                required
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-emerald-400 font-bold font-mono focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                placeholder="0.00"
              >
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                {{ $t('inventory.min_stock_level') }}
              </label>
              <input
                v-model="form.min_stock_level"
                type="number"
                step="0.001"
                class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-rose-400 font-mono focus:ring-2 focus:ring-rose-500 focus:outline-none"
                placeholder="0.00"
              >
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              {{ $t('common.notes') }}
            </label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full p-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="أي ملاحظات أو مواصفات خاصة بالصنف..."
            ></textarea>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showItemModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black shadow-lg shadow-amber-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>{{ $t('common.save') }}</span>
            </button>
          </div>
        </form>
      </AppModal>

      <!-- Quick Adjust Stock Modal -->
      <AppModal
        :show="showAdjustModal"
        :title="`${$t('inventory.adjust_stock')}: ${targetItem?.name}`"
        @close="showAdjustModal = false"
      >
        <form @submit.prevent="saveAdjustment" class="space-y-4 font-tajawal">
          <!-- Current Stock Info -->
          <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-2xl flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">{{ $t('inventory.current_stock') }}:</span>
            <span class="text-base font-black text-amber-400 font-mono">
              {{ formatQty(targetItem?.current_stock || 0) }} {{ targetItem?.unit }}
            </span>
          </div>

          <!-- Movement Type -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              {{ $t('inventory.movement_type') }} <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="adjustForm.movement_type"
              required
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
            >
              <option value="stock_adjustment_in">➕ تسوية جردية بالزيادة (إيداع مخزني)</option>
              <option value="stock_adjustment_out">➖ تسوية جردية بالعجز (خصم مخزني)</option>
              <option value="waste_out">🗑️ تسجيل هالك / تالف</option>
              <option value="stock_deposit_in">📦 توريد / رصيد افتتاحي إضافي</option>
            </select>
          </div>

          <!-- Quantity -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              {{ $t('common.quantity') }} <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="adjustForm.quantity"
              type="number"
              step="0.001"
              required
              autofocus
              class="w-full h-11 px-3 bg-slate-900 border border-slate-700 rounded-xl text-base font-bold text-amber-400 font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="0.000"
            >
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              سبب التسوية / ملاحظات
            </label>
            <input
              v-model="adjustForm.notes"
              type="text"
              class="w-full h-10 px-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
              placeholder="مثال: جرد دوري، عجز في الوزن، هالك تحميص..."
            >
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              @click="showAdjustModal = false"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
            >
              {{ $t('common.cancel') }}
            </button>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-black shadow-lg shadow-amber-500/20 disabled:opacity-50 cursor-pointer flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
              <span>تأكيد التسوية المخزنية</span>
            </button>
          </div>
        </form>
      </AppModal>
    </div>
  </SpaLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import SpaLayout from '../../Layouts/SpaLayout.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import EmptyState from '../../Components/Common/EmptyState.vue';
import AppModal from '../../Components/Common/AppModal.vue';
import api from '../../services/api';
import Swal from 'sweetalert2';
import {
    Plus,
    Search,
    TrendingUp,
    AlertTriangle,
    Package,
    Sliders,
    History,
    Pencil,
    Trash2
} from 'lucide-vue-next';

const items = ref([]);
const categories = ref([]);
const metrics = ref({
    total_items: 0,
    low_stock_count: 0,
    total_stock_value: 0,
});

const searchQuery = ref('');
const selectedCategory = ref('all');
const stockStatus = ref('all');
const isLoading = ref(false);
const isSubmitting = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

let debounceTimeout = null;

// Add / Edit State
const showItemModal = ref(false);
const editingItem = ref(null);
const form = reactive({
    name: '',
    code: '',
    category: '',
    unit: 'كجم',
    cost_price: '0.000',
    selling_price: '0.000',
    min_stock_level: '0.000',
    notes: '',
});

// Adjust Stock State
const showAdjustModal = ref(false);
const targetItem = ref(null);
const adjustForm = reactive({
    movement_type: 'stock_adjustment_in',
    quantity: '',
    notes: '',
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatQty = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
};

const fetchItems = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await api.get('/items', {
            params: {
                search: searchQuery.value,
                category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
                stock_status: stockStatus.value !== 'all' ? stockStatus.value : undefined,
                page: page,
                per_page: 20,
            },
        });
        items.value = response.data?.data || [];
        metrics.value = response.data?.summary || {
            total_items: 0,
            low_stock_count: 0,
            total_stock_value: 0,
        };
        categories.value = response.data?.categories || [];
        pagination.value = response.data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: 20,
            total: items.value.length,
        };
    } catch (error) {
        console.error('Failed to load items:', error);
    } finally {
        isLoading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchItems(1);
    }, 300);
};

const setStockStatus = (status) => {
    stockStatus.value = status;
    fetchItems(1);
};

onMounted(() => {
    fetchItems(1);
});

const openCreateModal = () => {
    editingItem.value = null;
    form.name = '';
    form.code = '';
    form.category = '';
    form.unit = 'كجم';
    form.cost_price = '0.000';
    form.selling_price = '0.000';
    form.min_stock_level = '0.000';
    form.notes = '';
    showItemModal.value = true;
};

const openEditModal = (item) => {
    editingItem.value = item;
    form.name = item.name;
    form.code = item.code || '';
    form.category = item.category || '';
    form.unit = item.unit || 'كجم';
    form.cost_price = item.cost_price;
    form.selling_price = item.selling_price;
    form.min_stock_level = item.min_stock_level;
    form.notes = item.notes || '';
    showItemModal.value = true;
};

const saveItem = async () => {
    isSubmitting.value = true;
    try {
        if (editingItem.value) {
            await api.put(`/items/${editingItem.value.id}`, form);
            Swal.fire({
                icon: 'success',
                title: 'تم التعديل',
                text: 'تم تعديل بيانات الصنف بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
        } else {
            await api.post('/items', form);
            Swal.fire({
                icon: 'success',
                title: 'تمت الإضافة',
                text: 'تم إضافة الصنف بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
        }
        showItemModal.value = false;
        await fetchItems(pagination.value.current_page);
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.userMessage || 'تعذر حفظ بيانات الصنف',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const openAdjustModal = (item) => {
    targetItem.value = item;
    adjustForm.movement_type = 'stock_adjustment_in';
    adjustForm.quantity = '';
    adjustForm.notes = 'تسوية مخزنية وجرد';
    showAdjustModal.value = true;
};

const saveAdjustment = async () => {
    if (!adjustForm.quantity || parseFloat(adjustForm.quantity) <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه',
            text: 'يرجى إدخال كمية تسوية صحيحة أكبر من الصفر',
        });
        return;
    }

    isSubmitting.value = true;
    try {
        await api.post(`/items/${targetItem.value.id}/adjust-stock`, {
            ...adjustForm,
            store_id: targetItem.value.store_stocks?.[0]?.store_id || 1,
        });
        Swal.fire({
            icon: 'success',
            title: 'تمت التسوية',
            text: 'تم تسجيل الحركة وتحديث رصيد المخزون بنجاح',
            timer: 1500,
            showConfirmButton: false,
        });
        showAdjustModal.value = false;
        await fetchItems(pagination.value.current_page);
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.userMessage || 'تعذر تسجيل تسوية المخزون',
        });
    } finally {
        isSubmitting.value = false;
    }
};

const deleteItem = async (item) => {
    if (!item.can_be_deleted) {
        const blockers = item.deletion_blockers?.join('\n- ') || '';
        Swal.fire({
            icon: 'warning',
            title: 'لا يمكن حذف الصنف',
            text: `يوجد ارتباطات عمليات تمنع الحذف:\n- ${blockers}`,
        });
        return;
    }

    const result = await Swal.fire({
        title: `حذف الصنف (${item.name})؟`,
        text: 'هل أنت متأكد من حذف هذا الصنف من النظام؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#f43f5e',
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`/items/${item.id}`);
            Swal.fire({
                icon: 'success',
                title: 'تم الحذف',
                text: 'تم حذف الصنف بنجاح',
                timer: 1500,
                showConfirmButton: false,
            });
            await fetchItems(pagination.value.current_page);
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: error.userMessage || 'تعذر حذف الصنف',
            });
        }
    }
};
</script>
