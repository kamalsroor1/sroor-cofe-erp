<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'return_type',
        'invoice_id',
        'purchase_id',
        'customer_id',
        'supplier_id',
        'user_id',
        'store_id',
        'total_amount',
        'return_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_amount' => 'decimal:3',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function store()
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'source');
    }
}
