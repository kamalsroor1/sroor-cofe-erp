<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'user_id',
        'store_id',
        'quotation_date',
        'valid_until',
        'pricing_tier', // wholesale, retail
        'status', // draft, sent, converted, expired, cancelled
        'converted_invoice_id',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'shipping_cost',
        'net_total',
        'notes',
        'terms_conditions',
    ];

    protected $casts = [
        'quotation_date'  => 'date',
        'valid_until'     => 'date',
        'subtotal'        => 'decimal:3',
        'discount_value'  => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'shipping_cost'   => 'decimal:3',
        'net_total'       => 'decimal:3',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function getTargetCustomerNameAttribute(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'عميل نقدي / جهة غير محددة';
    }

    public function getTargetCustomerPhoneAttribute(): ?string
    {
        return $this->customer?->phone ?? $this->customer_phone;
    }

    public function getPricingTierLabelAttribute(): string
    {
        return match ($this->pricing_tier) {
            'wholesale' => 'أسعار بيع جملة تجارية',
            'retail'    => 'أسعار بيع قطاعي',
            default     => 'أسعار خاصة',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->status === 'converted') {
            return 'تم التحويل لفاتورة بيع';
        }
        if ($this->valid_until && $this->valid_until->isPast() && $this->status !== 'converted') {
            return 'منتهي الصلاحية';
        }
        return match ($this->status) {
            'draft'     => 'مسودة عرض سعر',
            'sent'      => 'تم الإرسال للعميل',
            'cancelled' => 'ملغي',
            default     => 'مسودة',
        };
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && $this->status !== 'converted';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted' || !empty($this->converted_invoice_id);
    }
}
