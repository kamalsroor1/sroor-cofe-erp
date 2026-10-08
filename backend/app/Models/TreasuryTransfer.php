<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'from_method',
        'to_method',
        'amount',
        'transfer_fee',
        'store_id',
        'user_id',
        'transfer_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'amount' => 'decimal:3',
            'transfer_fee' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function getFromMethodEnumAttribute(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom($this->from_method);
    }

    public function getToMethodEnumAttribute(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom($this->to_method);
    }

    public function getFromMethodLabelAttribute(): string
    {
        return $this->from_method_enum?->label() ?? $this->from_method;
    }

    public function getToMethodLabelAttribute(): string
    {
        return $this->to_method_enum?->label() ?? $this->to_method;
    }

    public function getFromMethodIconAttribute(): string
    {
        return $this->from_method_enum?->icon() ?? '💵';
    }

    public function getToMethodIconAttribute(): string
    {
        return $this->to_method_enum?->icon() ?? '💵';
    }
}
