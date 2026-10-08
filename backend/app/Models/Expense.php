<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

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
