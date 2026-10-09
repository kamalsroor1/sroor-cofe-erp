<script setup>
/**
 * DataTable.vue — جدول بيانات تفاعلي وديناميكي موحد لمنظومة ERP
 *
 * @prop {Array}   columns      قائمة الأعمدة: [{ key, label, sortable?, align?, width?, hideOnMobile?, mono?, class? }]
 * @prop {Array}   rows         البيانات المراد عرضها في الجدول
 * @prop {Boolean} loading      حالة التحميل لعرض skeleton rows
 * @prop {String}  emptyMessage رسالة الحالة الفارغة
 * @prop {String}  sortKey      مفتاح العمود المفرز حالياً
 * @prop {String}  sortDir      اتجاه الفرز: 'asc' | 'desc'
 * @prop {Object}  pagination   كائن التصفح: { links, from, to, total }
 * @prop {Boolean} rowClickable إمكانية النقر على الصف بالكامل
 * @prop {Boolean} selectable   تفعيل تحديد الصفوف بخانات الاختيار (Checkboxes)
 * @prop {Array}   modelValue   الصفوف المحددة (v-model)
 * @prop {Number}  skeletonRows عدد صفوف التحميل الافتراضية
 *
 * @slots
 *  - #cell-[key]="{ row, value, index }"  — تخصيص خلية عمود معين
 *  - #header-[key]="{ column }"            — تخصيص رأس عمود معين
 *  - #mobile-card="{ row, index }"        — تخصيص كارت الموبايل بالكامل (اختياري)
 *  - #empty                                — حالة فارغة مخصصة
 *  - #actions="{ row, index }"             — اختصار لخانة الإجراءات (actions)
 */

import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Pagination from '@/Components/Common/Pagination.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import ErrorState from '@/Components/Common/ErrorState.vue';
import InlineErrorBar from '@/Components/Common/InlineErrorBar.vue';
import { ChevronUp, ChevronDown, PackageOpen } from 'lucide-vue-next';

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  emptyMessage: {
    type: String,
    default: '',
  },
  emptyTitle: {
    type: String,
    default: '',
  },
  emptyIcon: {
    type: [String, Object, Function],
    default: null,
  },
  sortKey: {
    type: String,
    default: null,
  },
  sortDir: {
    type: String,
    default: 'asc',
    validator: (v) => ['asc', 'desc'].includes(v),
  },
  pagination: {
    type: Object,
    default: null,
  },
  rowClickable: {
    type: Boolean,
    default: false,
  },
  selectable: {
    type: Boolean,
    default: false,
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  selectKey: {
    type: String,
    default: 'id',
  },
  skeletonRows: {
    type: Number,
    default: 5,
  },
  tableClass: {
    type: String,
    default: '',
  },
  cardClass: {
    type: String,
    default: '',
  },
  cardBreakpoint: {
    type: String,
    default: 'lg', // 'md' | 'lg' | 'xl' | 'none'
  },
  minWidth: {
    type: String,
    default: 'min-w-[640px]',
  },
  error: {
    type: [Boolean, String, Object],
    default: null,
  },
  errorMessage: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['sort', 'row-click', 'update:modelValue', 'retry', 'page-change']);

// ─── Table & Card Visibility Breakpoints ─────────────────────────────────────
const tableVisibilityClass = computed(() => {
  switch (props.cardBreakpoint) {
    case 'none':
      return 'block';
    case 'md':
      return 'hidden md:block';
    case 'xl':
      return 'hidden xl:block';
    case 'lg':
    default:
      return 'hidden lg:block';
  }
});

const cardVisibilityClass = computed(() => {
  switch (props.cardBreakpoint) {
    case 'none':
      return 'hidden';
    case 'md':
      return 'md:hidden';
    case 'xl':
      return 'xl:hidden';
    case 'lg':
    default:
      return 'lg:hidden';
  }
});

// ─── Horizontal Scroll Indicators (Edge Shadow) ──────────────────────────────
const tableContainer = ref(null);
const canScrollStart = ref(false);
const canScrollEnd = ref(false);

const updateScrollIndicators = () => {
  const el = tableContainer.value;
  if (!el) return;
  const { scrollWidth, clientWidth } = el;
  if (scrollWidth <= clientWidth + 2) {
    canScrollStart.value = false;
    canScrollEnd.value = false;
    return;
  }
  const maxScroll = scrollWidth - clientWidth;
  const currentScroll = Math.abs(el.scrollLeft);

  canScrollStart.value = currentScroll > 6;
  canScrollEnd.value = currentScroll < maxScroll - 6;
};

let resizeObserver = null;

onMounted(() => {
  nextTick(updateScrollIndicators);
  window.addEventListener('resize', updateScrollIndicators);
  if (typeof ResizeObserver !== 'undefined' && tableContainer.value) {
    resizeObserver = new ResizeObserver(() => updateScrollIndicators());
    resizeObserver.observe(tableContainer.value);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateScrollIndicators);
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
});

watch([() => props.rows?.length, () => props.columns?.length, () => props.loading], () => {
  nextTick(updateScrollIndicators);
});

// ─── Sorting Logic ────────────────────────────────────────────────────────────
const handleSort = (col) => {
  if (!col.sortable) return;
  emit('sort', col.key);
};

// ─── Row Click ────────────────────────────────────────────────────────────────
const handleRowClick = (row, event) => {
  // Avoid triggering row-click if interactive elements inside were clicked
  const target = event?.target;
  if (
    target &&
    (target.closest('button') || target.closest('a') || target.closest('input') || target.closest('select'))
  ) {
    return;
  }
  if (props.rowClickable) {
    emit('row-click', row);
  }
};

// ─── Selection Logic ──────────────────────────────────────────────────────────
const isAllSelected = computed(() => {
  if (!props.rows || props.rows.length === 0) return false;
  return props.rows.every((r) => props.modelValue.includes(r[props.selectKey]));
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    emit('update:modelValue', []);
  } else {
    const allIds = props.rows.map((r) => r[props.selectKey]);
    emit('update:modelValue', allIds);
  }
};

const toggleSelectRow = (row) => {
  const id = row[props.selectKey];
  const current = [...props.modelValue];
  const idx = current.indexOf(id);
  if (idx > -1) {
    current.splice(idx, 1);
  } else {
    current.push(id);
  }
  emit('update:modelValue', current);
};

const isRowSelected = (row) => {
  return props.modelValue.includes(row[props.selectKey]);
};

// ─── Helper Functions ─────────────────────────────────────────────────────────
const getCellValue = (row, key) => {
  if (!row || !key) return null;
  return key.split('.').reduce((obj, k) => obj?.[k], row);
};

const alignClass = (col) => {
  if (col.align === 'center') return 'text-center';
  if (col.align === 'left') return 'text-left';
  if (col.align === 'right') return 'text-right';
  if (col.align === 'end') return 'text-end';
  return 'text-start';
};

const visibleOnMobile = (col) => col.hideOnMobile !== true;
</script>

<template>
  <div class="font-tajawal space-y-4">
    <!-- Inline Error Bar for stale rows -->
    <InlineErrorBar
      v-if="error && rows && rows.length > 0"
      :message="typeof error === 'string' ? error : errorMessage"
      @retry="$emit('retry')"
    />

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- Table View (Hidden on Small/Tablet Screens per cardBreakpoint) -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div :class="tableVisibilityClass" class="relative rounded-2xl overflow-hidden group/table">
      <!-- Start Edge Shadow Indicator (visible when scrolled) -->
      <div
        v-show="canScrollStart"
        class="pointer-events-none absolute inset-y-0 start-0 w-8 z-10 bg-gradient-to-r rtl:bg-gradient-to-l from-slate-900/10 dark:from-black/40 to-transparent transition-opacity duration-200"
        aria-hidden="true"
      />

      <!-- Scrollable Container -->
      <div ref="tableContainer" class="overflow-x-auto scrollbar-thin" @scroll.passive="updateScrollIndicators">
        <table class="w-full text-xs text-start" :class="[tableClass, minWidth]" data-table="true">
          <!-- thead -->
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
              <!-- Select All Checkbox -->
              <th v-if="selectable" class="pb-3 coarse:pb-2.5 w-12 text-center">
                <label class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    :indeterminate="modelValue.length > 0 && !isAllSelected"
                    class="w-4 h-4 rounded text-theme-primary focus:ring-theme-primary cursor-pointer"
                    @change="toggleSelectAll"
                  />
                </label>
              </th>

              <!-- Data Columns -->
              <th
                v-for="col in columns"
                :key="col.key"
                class="pb-3 coarse:pb-2.5 px-3"
                :class="[
                  alignClass(col),
                  col.width ? col.width : '',
                  col.sortable
                    ? 'cursor-pointer select-none hover:text-slate-800 dark:hover:text-slate-900 dark:text-slate-200 transition'
                    : '',
                ]"
                @click="handleSort(col)"
              >
                <slot :name="`header-${col.key}`" :column="col">
                  <span
                    class="inline-flex items-center gap-1.5"
                    :class="col.align === 'left' ? 'flex-row-reverse' : ''"
                  >
                    <span>{{ col.label }}</span>
                    <span v-if="col.sortable" class="inline-flex flex-col opacity-60">
                      <ChevronUp
                        class="w-3 h-3 -mb-1"
                        :class="
                          sortKey === col.key && sortDir === 'asc' ? 'text-theme-primary opacity-100' : 'opacity-30'
                        "
                      />
                      <ChevronDown
                        class="w-3 h-3"
                        :class="
                          sortKey === col.key && sortDir === 'desc' ? 'text-theme-primary opacity-100' : 'opacity-30'
                        "
                      />
                    </span>
                  </span>
                </slot>
              </th>
            </tr>
          </thead>

          <!-- tbody -->
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <!-- ── Loading Skeleton ── -->
            <template v-if="loading">
              <tr v-for="n in skeletonRows" :key="`sk-${n}`" class="animate-pulse">
                <td v-if="selectable" class="py-3.5 text-center">
                  <div class="w-4 h-4 bg-slate-200 dark:bg-slate-800 rounded mx-auto"></div>
                </td>
                <td v-for="col in columns" :key="col.key" class="py-3.5 px-3">
                  <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded-full w-3/4"></div>
                </td>
              </tr>
            </template>

            <!-- ── Data Rows ── -->
            <template v-else-if="rows && rows.length > 0">
              <tr
                v-for="(row, index) in rows"
                :key="row[selectKey] ?? index"
                class="transition"
                :class="[
                  rowClickable
                    ? 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40'
                    : 'hover:bg-slate-50/60 dark:hover:bg-slate-800/20',
                  selectable && isRowSelected(row) ? 'bg-theme-light dark:bg-theme-light' : '',
                ]"
                @click="handleRowClick(row, $event)"
              >
                <!-- Row Checkbox -->
                <td v-if="selectable" class="py-1 text-center" @click.stop>
                  <label class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] cursor-pointer">
                    <input
                      type="checkbox"
                      :checked="isRowSelected(row)"
                      class="w-4 h-4 rounded text-theme-primary focus:ring-theme-primary cursor-pointer"
                      @change="toggleSelectRow(row)"
                    />
                  </label>
                </td>

                <!-- Column Cells -->
                <td
                  v-for="col in columns"
                  :key="col.key"
                  class="py-3.5 px-3"
                  :class="[alignClass(col), col.mono ? 'font-mono' : '', col.class || '']"
                >
                  <slot
                    v-if="$slots[`cell-${col.key}`]"
                    :name="`cell-${col.key}`"
                    :row="row"
                    :value="getCellValue(row, col.key)"
                    :index="index"
                  />
                  <div
                    v-else-if="col.key === 'actions' && $slots['actions']"
                    class="data-table-actions inline-flex items-center gap-1"
                  >
                    <slot name="actions" :row="row" :index="index" />
                  </div>
                  <span v-else class="text-slate-700 dark:text-slate-200">
                    {{ getCellValue(row, col.key) ?? '—' }}
                  </span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- End Edge Shadow Indicator (visible when more horizontal content exists) -->
      <div
        v-show="canScrollEnd"
        class="pointer-events-none absolute inset-y-0 end-0 w-8 z-10 bg-gradient-to-l rtl:bg-gradient-to-r from-slate-900/10 dark:from-black/40 to-transparent transition-opacity duration-200"
        aria-hidden="true"
      />
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- Responsive Cards View (Visible per cardBreakpoint)     -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div :class="cardVisibilityClass" class="space-y-3">
      <!-- Loading Skeleton Cards -->
      <template v-if="loading">
        <div
          v-for="n in skeletonRows"
          :key="`msk-${n}`"
          class="animate-pulse p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 space-y-3"
        >
          <div class="flex justify-between items-center pb-2 border-b border-slate-200 dark:border-slate-800">
            <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded-full w-1/3"></div>
            <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded-full w-1/4"></div>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded-full w-3/4"></div>
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded-full w-2/3"></div>
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded-full w-1/2"></div>
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded-full w-3/5"></div>
          </div>
        </div>
      </template>

      <!-- Data Cards -->
      <template v-else-if="rows && rows.length > 0">
        <div
          v-for="(row, index) in rows"
          :key="row[selectKey] ?? index"
          class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs transition"
          :class="[
            rowClickable ? 'cursor-pointer active:scale-99' : '',
            selectable && isRowSelected(row) ? 'ring-2 ring-theme-primary bg-theme-light dark:bg-theme-light' : '',
            cardClass,
          ]"
          @click="handleRowClick(row, $event)"
        >
          <!-- Custom Mobile Card Override Slot -->
          <slot v-if="$slots['mobile-card']" name="mobile-card" :row="row" :index="index" />

          <!-- Default Automatic Card -->
          <div v-else class="space-y-2.5 font-tajawal">
            <!-- Top Header Row: First Column + Actions / Last Column -->
            <div
              class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2.5"
            >
              <div class="flex items-center gap-2 min-w-0">
                <label
                  v-if="selectable"
                  class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] -m-2 cursor-pointer shrink-0"
                  @click.stop
                >
                  <input
                    type="checkbox"
                    :checked="isRowSelected(row)"
                    class="w-4 h-4 rounded text-theme-primary focus:ring-theme-primary cursor-pointer"
                    @change="toggleSelectRow(row)"
                  />
                </label>
                <div class="min-w-0">
                  <slot
                    v-if="$slots[`cell-${columns[0].key}`]"
                    :name="`cell-${columns[0].key}`"
                    :row="row"
                    :value="getCellValue(row, columns[0].key)"
                    :index="index"
                  />
                  <span v-else class="font-black text-sm text-slate-900 dark:text-white truncate block">
                    {{ getCellValue(row, columns[0].key) ?? '—' }}
                  </span>
                </div>
              </div>

              <!-- Right Side Badge / Actions of Header -->
              <div class="shrink-0 flex items-center gap-1 data-table-actions">
                <slot
                  v-if="columns.length > 1 && $slots[`cell-${columns[columns.length - 1].key}`]"
                  :name="`cell-${columns[columns.length - 1].key}`"
                  :row="row"
                  :value="getCellValue(row, columns[columns.length - 1].key)"
                  :index="index"
                />
                <slot v-else-if="$slots['actions']" name="actions" :row="row" :index="index" />
              </div>
            </div>

            <!-- Body Grid of Columns -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
              <div
                v-for="col in columns
                  .slice(1, columns[columns.length - 1]?.key === 'actions' ? -1 : undefined)
                  .filter(visibleOnMobile)"
                :key="col.key"
                class="space-y-0.5"
              >
                <span class="text-[10px] text-slate-400 dark:text-slate-500 font-bold block">{{ col.label }}</span>
                <slot
                  v-if="$slots[`cell-${col.key}`]"
                  :name="`cell-${col.key}`"
                  :row="row"
                  :value="getCellValue(row, col.key)"
                  :index="index"
                />
                <span
                  v-else
                  class="text-xs font-bold text-slate-800 dark:text-slate-200"
                  :class="col.mono ? 'font-mono' : ''"
                >
                  {{ getCellValue(row, col.key) ?? '—' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- Error State OR Empty State                               -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div v-if="!loading && (!rows || rows.length === 0)">
      <template v-if="error">
        <slot name="error">
          <ErrorState :message="typeof error === 'string' ? error : errorMessage" @retry="$emit('retry')" />
        </slot>
      </template>
      <template v-else>
        <slot name="empty">
          <EmptyState
            :title="emptyTitle || emptyMessage || $t('common.no_data')"
            :description="emptyTitle && emptyMessage ? emptyMessage : ''"
            :icon="emptyIcon || PackageOpen"
          >
            <slot name="empty-actions">
              <slot name="empty-action" />
            </slot>
          </EmptyState>
        </slot>
      </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- Pagination                                               -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <Pagination
      v-if="pagination && !loading && rows && rows.length > 0"
      :links="pagination.links"
      :from="pagination.from"
      :to="pagination.to"
      :total="pagination.total"
      :current-page="pagination.current_page || pagination.currentPage"
      :last-page="pagination.last_page || pagination.lastPage"
      :per-page="pagination.per_page || pagination.perPage"
      :pagination="pagination"
      @page-change="$emit('page-change', $event)"
    />
  </div>
</template>
