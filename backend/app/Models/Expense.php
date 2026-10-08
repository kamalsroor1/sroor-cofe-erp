<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $expense_number
 * @property string $category
 * @property string $title
 * @property string $amount
 * @property Carbon|null $expense_date
 * @property string $payment_method
 * @property int|null $user_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $store_id
 * @property Carbon|null $deleted_at
 * @property string $cost_center
 */
class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_number',
        'category',
        'cost_center',
        'title',
        'amount',
        'expense_date',
        'payment_method',
        'user_id',
        'store_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'expense_date' => 'date',
    ];

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

    public function getCostCenterLabelAttribute(): string
    {
        return match ($this->cost_center) {
            'rent' => 'إيجارات مقرات وفروع',
            'utilities' => 'كهرباء ومياه وغاز ومرافق',
            'salaries' => 'رواتب وعمالة وإكراميات',
            'vehicles' => 'وقود وزيوت وصيانة سيارات',
            'maintenance' => 'صيانة معدات وديكورات',
            'packaging' => 'مطبوعات وكراتين وتعبئة',
            'hospitality' => 'ضيافة ونظافة وبوفيه',
            'marketing' => 'تسويق وإعلانات ودعاية',
            'shipping' => 'شحن ونولون وتوصيل خارجي',
            default => 'مصاريف تشغيلية ونثريات عامة',
        };
    }
}
