<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📦 إدارة الأصناف والمخزون</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">قائمة الأصناف، الرصيد المتاح، أسعار التكلفة والبيع، وتعديل بيانات المنتجات</p>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <a href="{{ route('items.export.csv') }}" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 flex items-center justify-center gap-2 transition-colors">
                📊 تصدير CSV
            </a>
            @can('items.create')
            <button wire:click="openCreateModal" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition-all cursor-pointer">
                <span>+ إضافة صنف جديد</span>
            </button>
            @endcan
        </div>
    </div>

    @if (session()->has('success'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2">
        <span>✅ {{ session('success') }}</span>
    </div>
    @endif

    <!-- Search & Filter Bar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="w-full sm:w-80">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="بحث بكود، اسم، أو قسم الصنف..." 
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-emerald-500"
            >
        </div>
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">الحالة:</span>
            <button wire:click="$set('filterStatus', 'active')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStatus === 'active' ? 'bg-emerald-600 text-white border-emerald-500' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400' }}">النشطة</button>
            <button wire:click="$set('filterStatus', 'disabled')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 {{ $filterStatus === 'disabled' ? 'bg-amber-600 text-white border-amber-500' : 'border-slate-200 dark:border-slate-800 text-amber-600 dark:text-amber-400' }}">
                <span>المعطلة (مخفية)</span>
                @if($disabledCount > 0)
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $filterStatus === 'disabled' ? 'bg-white text-amber-700' : 'bg-amber-500/20 text-amber-600' }} font-mono font-bold">{{ $disabledCount }}</span>
                @endif
            </button>
            <button wire:click="$set('filterStatus', 'trashed')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs flex items-center gap-1 {{ $filterStatus === 'trashed' ? 'bg-rose-600 text-white border-rose-500' : 'border-slate-200 dark:border-slate-800 text-rose-600 dark:text-rose-400' }}">
                <span>سلة المحذوفات</span>
                @if($trashedCount > 0)
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $filterStatus === 'trashed' ? 'bg-white text-rose-600' : 'bg-rose-500/20 text-rose-600' }} font-mono font-bold">{{ $trashedCount }}</span>
                @endif
            </button>
            <button wire:click="$set('filterStatus', 'all')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStatus === 'all' ? 'bg-slate-700 text-white border-slate-600' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400' }}">الكل</button>

            <span class="text-slate-300 dark:text-slate-700 mx-1">|</span>

            <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">المخزون:</span>
            <button wire:click="$set('filterStock', 'all')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStock === 'all' ? 'bg-slate-200 dark:bg-slate-800 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 dark:text-slate-400' }}">الكل</button>
            <button wire:click="$set('filterStock', 'in_stock')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStock === 'in_stock' ? 'bg-emerald-600 text-white border-emerald-500 shadow-sm' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">📦 متوفر كمية (>0)</button>
            <button wire:click="$set('filterStock', 'low')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStock === 'low' ? 'bg-amber-500/20 border-amber-500/40 text-amber-700 dark:text-amber-400' : 'border-transparent text-slate-500 dark:text-slate-400' }}">⚠️ قرب النفاد</button>
            <button wire:click="$set('filterStock', 'out')" class="px-2.5 py-1.5 rounded-lg font-bold border transition-colors cursor-pointer text-xs {{ $filterStock === 'out' ? 'bg-rose-100 dark:bg-rose-950 border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300' : 'border-transparent text-slate-500 dark:text-slate-400' }}">نفد (0)</button>
        </div>
    </div>

    <!-- Mobile Cards View (< 640px) -->
    <div class="sm:hidden space-y-3">
        @forelse($items as $item)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2.5">
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm">{{ $item->name }}</h3>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="font-mono text-[11px] text-emerald-600 dark:text-emerald-400 font-bold">{{ $item->code }}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ $item->category ?? 'عام' }}</span>
                    </div>
                </div>
                <div>
                    @if($item->isLowStock())
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">ناقص بالمخزن</span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">متوفر</span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-{{ auth()->user()?->can('items.view_cost') ? '3' : '2' }} gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 text-center text-xs">
                <div>
                    <span class="text-[10px] text-slate-400 block">الرصيد:</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ number_format($item->current_stock, 2) }} {{ $item->unit }}</span>
                </div>
                @can('items.view_cost')
                <div>
                    <span class="text-[10px] text-slate-400 block">التكلفة:</span>
                    <span class="font-mono text-slate-600 dark:text-slate-400">{{ number_format($item->cost_price, 2) }}</span>
                </div>
                @endcan
                <div>
                    <span class="text-[10px] text-slate-400 block">البيع:</span>
                    <span class="font-mono font-black text-emerald-600 dark:text-emerald-400">{{ number_format($item->selling_price, 2) }}</span>
                </div>
            </div>

            @can('items.edit')
            <div class="flex justify-end pt-1">
                <button 
                    wire:click="openEditModal({{ $item->id }})" 
                    class="w-full py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-700 transition-colors text-center cursor-pointer"
                >
                    ✏️ تعديل بيانات الصنف
                </button>
            </div>
            @endcan
        </div>
        @empty
        <div class="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-xs">
            لا توجد أصناف مطابقة للبحث
        </div>
        @endforelse
    </div>

    <!-- Desktop / Tablet Table View (>= 640px) -->
    <div class="hidden sm:block bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-3.5">كود الصنف</th>
                        <th class="p-3.5">اسم الصنف</th>
                        <th class="p-3.5">القسم</th>
                        <th class="p-3.5">الوحدة</th>
                        <th class="p-3.5">الرصيد الحالي</th>
                        @can('items.view_cost')
                        <th class="p-3.5">سعر التكلفة</th>
                        @endcan
                        <th class="p-3.5">سعر البيع</th>
                        <th class="p-3.5">الحد الأدنى</th>
                        <th class="p-3.5 text-center">الحالة</th>
                        <th class="p-3.5 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @forelse($items as $item)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $item->code }}</td>
                        <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $item->name }}</td>
                        <td class="p-3.5 text-slate-600 dark:text-slate-400">{{ $item->category ?? 'عام' }}</td>
                        <td class="p-3.5 text-slate-600 dark:text-slate-400">{{ $item->unit }}</td>
                        <td class="p-3.5 font-mono font-bold {{ $item->isLowStock() ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}">
                            {{ number_format($item->current_stock, 3) }}
                        </td>
                        @can('items.view_cost')
                        <td class="p-3.5 font-mono text-slate-700 dark:text-slate-300">{{ number_format($item->cost_price, 2) }} ج.م</td>
                        @endcan
                        <td class="p-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($item->selling_price, 2) }} ج.م</td>
                        <td class="p-3.5 font-mono text-slate-500 dark:text-slate-400">{{ number_format($item->min_stock_level, 2) }}</td>
                        <td class="p-3.5 text-center">
                            @if($item->trashed())
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">محذوف</span>
                            @elseif(!$item->is_active)
                                <button 
                                    wire:click="toggleActive({{ $item->id }})" 
                                    title="الصنف معطل ومخفي من شاشة المبيعات - انقر لإعادة التفعيل"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20 hover:bg-slate-500/20 transition-colors cursor-pointer"
                                >
                                    ⏸️ معطل
                                </button>
                            @elseif($item->isLowStock())
                                <button 
                                    wire:click="toggleActive({{ $item->id }})" 
                                    title="الصنف نشط ولكن رصيده قليل - انقر لتعطيله"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition-colors cursor-pointer"
                                >
                                    ⚠️ ناقص
                                </button>
                            @else
                                <button 
                                    wire:click="toggleActive({{ $item->id }})" 
                                    title="الصنف نشط ومتاح في البيع - انقر لتعطيله"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition-colors cursor-pointer"
                                >
                                    ✅ نشط
                                </button>
                            @endif
                        </td>
                        <td class="p-3.5 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($item->trashed())
                                    @can('trash.access')
                                    <button 
                                        wire:click="restoreItem({{ $item->id }})" 
                                        title="استعادة الصنف"
                                        class="px-2.5 py-1 bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-400 hover:text-white rounded-lg text-xs font-bold border border-emerald-500/30 transition-colors inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <span>♻️ استعادة</span>
                                    </button>
                                    @endcan
                                @else
                                    @can('items.view')
                                    <a 
                                        href="{{ route('items.movements', $item->id) }}" 
                                        title="كارت حركة الصنف"
                                        class="px-2 py-1 bg-indigo-500/10 hover:bg-indigo-600 text-indigo-600 dark:text-indigo-400 hover:text-white rounded-lg text-xs font-bold border border-indigo-500/20 transition-colors inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <span>📋</span>
                                    </a>
                                    @endcan

                                    @can('items.edit')
                                    <button 
                                        wire:click="openAdjustmentModal({{ $item->id }})" 
                                        title="تسوية جردية وتصحيح رصيد المخزن"
                                        class="px-2 py-1 bg-amber-500/10 hover:bg-amber-600 text-amber-600 dark:text-amber-400 hover:text-white rounded-lg text-xs font-bold border border-amber-500/20 transition-colors inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <span>⚖️</span>
                                    </button>

                                    <button 
                                        wire:click="openEditModal({{ $item->id }})" 
                                        title="تعديل بيانات الصنف"
                                        class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 text-slate-700 dark:text-slate-300 hover:text-white rounded-lg text-xs font-bold border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <span>✏️</span>
                                    </button>
                                    @endcan

                                    @can('items.delete')
                                    <button 
                                        wire:click="deleteItem({{ $item->id }})" 
                                        title="حذف الصنف (مسموح فقط في حال عدم وجود معاملات تاريخية)"
                                        class="px-2 py-1 bg-rose-500/10 hover:bg-rose-600 text-rose-600 dark:text-rose-400 hover:text-white rounded-lg text-xs font-bold border border-rose-500/20 transition-colors inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <span>🗑️</span>
                                    </button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="p-12 text-center text-slate-400">لا توجد أصناف مطابقة للبحث</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $items->links() }}
        </div>
    </div>

    <div class="sm:hidden p-2">
        {{ $items->links() }}
    </div>

    <!-- Create & Edit Item Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-lg p-5 sm:p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <span>{{ $isEditMode ? '✏️ تعديل بيانات الصنف' : '📦 إضافة صنف جديد للمخزون' }}</span>
                </h3>
                <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</button>
            </div>

            <form wire:submit.prevent="saveItem" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">كود الصنف (الباركود):</label>
                        <input type="text" wire:model="code" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-mono">
                        @error('code') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم الصنف: *</label>
                        <input type="text" wire:model="name" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                        @error('name') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">القسم / التصنيف:</label>
                        <input type="text" wire:model="category" placeholder="مثال: مواد خام / بضاعة جاهزة / معلبات / مستلزمات" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                        @error('category') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الوحدة: *</label>
                        <select wire:model="unit" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="كجم">كجم (كيلوغرام)</option>
                            <option value="شيكارة">شيكارة</option>
                            <option value="علبة">علبة</option>
                            <option value="كرتونة">كرتونة</option>
                            <option value="قطعة">قطعة</option>
                        </select>
                        @error('unit') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">سعر التكلفة: *</label>
                        <input type="number" step="0.001" wire:model="cost_price" @disabled($costLocked) class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
                        @if($costLocked)
                            <span class="block text-slate-500 dark:text-slate-400 text-[10px] mt-1">التكلفة تُحسب تلقائياً من فواتير الشراء (متوسط مرجح) لأن للصنف حركات مخزنية.</span>
                        @endif
                        @error('cost_price') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">سعر البيع: *</label>
                        <input type="number" step="0.001" wire:model="selling_price" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                        @error('selling_price') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الحد الأدنى للتنبيه:</label>
                        <input type="number" step="0.001" wire:model="min_stock_level" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                        @error('min_stock_level') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if(!$isEditMode)
                <div class="p-3 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رصيد أول المدة (الافتتاحي):</label>
                    <input type="number" step="0.001" wire:model="current_stock" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 cursor-pointer">
                        {{ $isEditMode ? 'حفظ التعديلات' : 'إضافة الصنف' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Stock Adjustment Modal (نافذة التسوية الجردية وتصحيح رصيد المخزن) -->
    @if($showAdjustmentModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-lg p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-amber-600 dark:text-amber-400 text-base flex items-center gap-2">
                    <span>⚖️ تسوية جردية وتصحيح رصيد الصنف</span>
                </h3>
                <button wire:click="$set('showAdjustmentModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</button>
            </div>

            <!-- Item Info Banner -->
            <div class="p-3.5 bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px]">الصنف المطلوب تسويته:</span>
                    <strong class="text-slate-900 dark:text-white text-sm">{{ $adjustItemName }}</strong>
                </div>
                <div class="text-left">
                    <span class="text-slate-400 block text-[10px]">الرصيد المسجل حالياً:</span>
                    <span class="font-mono font-bold text-slate-700 dark:text-slate-300 text-sm">{{ number_format($currentRecordedStock, 3) }} {{ $adjustItemUnit }}</span>
                </div>
            </div>

            <form wire:submit.prevent="saveStockAdjustment" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        الرصيد الفعلي على الميزان / بالجرد الفعلي ({{ $adjustItemUnit }}): <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="number" 
                        step="0.001" 
                        wire:model.live="actualCountStock" 
                        required 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-3 text-base font-mono font-black text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"
                    >
                    @error('actualCountStock') <span class="text-rose-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Difference Calculator Preview -->
                @php
                    $diffVal = bcsub((string)($actualCountStock ?: '0'), (string)$currentRecordedStock, 3);
                @endphp
                <div class="p-3 rounded-xl border text-xs flex items-center justify-between {{ bccomp($diffVal, '0.000', 3) > 0 ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300' : (bccomp($diffVal, '0.000', 3) < 0 ? 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400') }}">
                    <div class="flex items-center gap-1.5 font-bold">
                        @if(bccomp($diffVal, '0.000', 3) > 0)
                            <span>📈</span>
                            <span>فارق تسوية بالزيادة (+):</span>
                        @elseif(bccomp($diffVal, '0.000', 3) < 0)
                            <span>📉</span>
                            <span>فارق تسوية بالعجز/الهالك (-):</span>
                        @else
                            <span>⚖️</span>
                            <span>لا يوجد فارق:</span>
                        @endif
                    </div>
                    <span class="font-mono font-black text-sm" dir="ltr">
                        {{ number_format($diffVal, 3) }} {{ $adjustItemUnit }}
                    </span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">سبب التسوية الجردية: <span class="text-rose-500">*</span></label>
                    <select wire:model="adjustmentReason" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                        <option value="عجز جرد وتصحيح وزن">عجز جرد وتصحيح وزن خطأ</option>
                        <option value="هالك تحميص وتشغيل">هالك تحميص وتشغيل</option>
                        <option value="بضاعة تالفة / كسر / انسكاب">بضاعة تالفة / كسر / انسكاب</option>
                        <option value="زيادة جرد / خطأ تسجيل سابق">زيادة جرد / خطأ تسجيل سابق</option>
                        <option value="تسوية جرد دوري معتمد">تسوية جرد دوري معتمد</option>
                        <option value="أخرى">أخرى (موضحة بالملاحظات)</option>
                    </select>
                    @error('adjustmentReason') <span class="text-rose-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات توضيحية إضافية:</label>
                    <input type="text" wire:model="adjustmentNotes" placeholder="أي تفاصيل لمدير الفرع أو المحاسب..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" wire:click="$set('showAdjustmentModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer">إلغاء</button>
                    <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-amber-600/30 cursor-pointer flex items-center gap-1.5">
                        <span>💾 تأكيد وتطبيق التسوية</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
