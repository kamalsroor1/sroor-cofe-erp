<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'module',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function store()
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function getModuleBadgeAttribute(): array
    {
        return match ($this->module) {
            'sales' => ['icon' => '🛒', 'label' => 'المبيعات و POS'],
            'inventory' => ['icon' => '📦', 'label' => 'الأصناف والمخزون'],
            'shifts' => ['icon' => '💵', 'label' => 'الخزينة والورديات'],
            'purchases' => ['icon' => '🚚', 'label' => 'المشتريات والتوريد'],
            'expenses' => ['icon' => '💸', 'label' => 'المصروفات'],
            'contacts' => ['icon' => '👥', 'label' => 'العملاء والموردين'],
            'auth' => ['icon' => '🔐', 'label' => 'الأمان والدخول'],
            'treasury' => ['icon' => '🔁', 'label' => 'حسابات الخزينة'],
            'system' => ['icon' => '⚙️', 'label' => 'إدارة النظام'],
            default => ['icon' => '📋', 'label' => $this->module ?: 'عام'],
        };
    }

    public function getActionBadgeAttribute(): array
    {
        return match ($this->action) {
            'created', 'invoice_created', 'purchase_created' => [
                'label' => 'إضافة / إصدار',
                'bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
            ],
            'updated', 'item_price_changed', 'customer_updated' => [
                'label' => 'تعديل',
                'bg' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30',
            ],
            'cancelled', 'invoice_cancelled' => [
                'label' => 'إلغاء',
                'bg' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30',
            ],
            'deleted', 'expense_deleted' => [
                'label' => 'حذف / أرشفة',
                'bg' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30',
            ],
            'transfer', 'treasury_transfer' => [
                'label' => 'تحويل مالي',
                'bg' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-500/30',
            ],
            'login', 'login_success' => [
                'label' => 'تسجيل دخول',
                'bg' => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/30',
            ],
            default => [
                'label' => $this->action_label,
                'bg' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            ],
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'invoice_created' => '🧾 إصدار فاتورة مبيعات',
            'invoice_cancelled' => '🚫 إلغاء فاتورة مبيعات',
            'purchase_created' => '🛒 توريد فاتورة مشتريات',
            'item_price_changed' => '🏷️ تعديل سعر صنف',
            'expense_created' => '💸 تسجيل مصروف تشغيلي',
            'expense_deleted' => '🗑️ أرشفة / حذف مصروف',
            'treasury_transfer' => '🔁 تحويل مالي بين الخزن',
            'customer_created' => '👤 إضافة عميل جديد',
            'customer_updated' => '✏️ تعديل بيانات عميل',
            'settings_updated' => '⚙️ تحديث إعدادات النظام',
            default => $this->action,
        };
    }
}
