<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $store_id
 * @property string $module
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property array|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getModuleBadgeAttribute(): array
    {
        return match ($this->module) {
            'sales' => ['icon' => 'shopping-cart', 'label' => __('activity.modules.sales')],
            'inventory' => ['icon' => 'package', 'label' => __('activity.modules.inventory')],
            'shifts' => ['icon' => 'banknote', 'label' => __('activity.modules.shifts')],
            'purchases' => ['icon' => 'truck', 'label' => __('activity.modules.purchases')],
            'expenses' => ['icon' => 'receipt', 'label' => __('activity.modules.expenses')],
            'contacts' => ['icon' => 'users', 'label' => __('activity.modules.contacts')],
            'auth' => ['icon' => 'lock', 'label' => __('activity.modules.auth')],
            'treasury' => ['icon' => 'arrow-left-right', 'label' => __('activity.modules.treasury')],
            'system' => ['icon' => 'sliders', 'label' => __('activity.modules.system')],
            default => ['icon' => 'clipboard-list', 'label' => $this->module ?: __('activity.modules.general')],
        };
    }

    public function getActionBadgeAttribute(): array
    {
        return match ($this->action) {
            'created', 'invoice_created', 'purchase_created' => [
                'label' => __('activity.action_badges.created'),
                'bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
            ],
            'updated', 'item_price_changed', 'customer_updated' => [
                'label' => __('activity.action_badges.updated'),
                'bg' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30',
            ],
            'cancelled', 'invoice_cancelled' => [
                'label' => __('activity.action_badges.cancelled'),
                'bg' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30',
            ],
            'deleted', 'expense_deleted' => [
                'label' => __('activity.action_badges.deleted'),
                'bg' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30',
            ],
            'transfer', 'treasury_transfer' => [
                'label' => __('activity.action_badges.transfer'),
                'bg' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-500/30',
            ],
            'login', 'login_success' => [
                'label' => __('activity.action_badges.login'),
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
            'invoice_created' => __('activity.action_labels.invoice_created'),
            'invoice_cancelled' => __('activity.action_labels.invoice_cancelled'),
            'purchase_created' => __('activity.action_labels.purchase_created'),
            'item_price_changed' => __('activity.action_labels.item_price_changed'),
            'expense_created' => __('activity.action_labels.expense_created'),
            'expense_deleted' => __('activity.action_labels.expense_deleted'),
            'treasury_transfer' => __('activity.action_labels.treasury_transfer'),
            'customer_created' => __('activity.action_labels.customer_created'),
            'customer_updated' => __('activity.action_labels.customer_updated'),
            'settings_updated' => __('activity.action_labels.settings_updated'),
            default => $this->action,
        };
    }
}
