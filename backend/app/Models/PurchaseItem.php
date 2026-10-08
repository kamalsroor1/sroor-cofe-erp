<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_id',
        'item_id',
        'quantity',
        'base_cost_price',
        'allocated_expense',
        'cost_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'base_cost_price' => 'decimal:3',
            'allocated_expense' => 'decimal:3',
            'cost_price' => 'decimal:3',
            'total_price' => 'decimal:3',
        ];
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    public function item()
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
