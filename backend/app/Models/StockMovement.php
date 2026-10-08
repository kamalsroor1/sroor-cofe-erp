<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'movement_type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_cost',
        'source_type',
        'source_id',
        'document_number',
        'user_id',
        'store_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'unit_cost' => 'decimal:3',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function store()
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function source()
    {
        return $this->morphTo();
    }
}
