<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_store_id',
        'to_store_id',
        'product_id',
        'quantity',
        'status',
        'requested_by',
        'approved_by',
        'remarks',
    ];

    protected static function booted()
    {
        static::updated(function ($stockTransfer) {
            // Run only if status changed to 'approved'
            if ($stockTransfer->isDirty('status') && $stockTransfer->status === 'approved') {
                DB::transaction(function () use ($stockTransfer) {
                    // Decrease quantity from fromStore inventory
                    $fromInventory = \App\Models\StoreInventory::where('store_id', $stockTransfer->from_store_id)
                        ->where('product_id', $stockTransfer->product_id)
                        ->first();

                    if ($fromInventory) {
                        // Prevent negative quantities
                        $fromInventory->quantity = max(0, $fromInventory->quantity - $stockTransfer->quantity);
                        $fromInventory->save();
                    }

                    // Increase quantity in toStore inventory, create if not exists
                    $toInventory = \App\Models\StoreInventory::firstOrCreate(
                        [
                            'store_id' => $stockTransfer->to_store_id,
                            'product_id' => $stockTransfer->product_id,
                        ],
                        [
                            'quantity' => 0,
                        ]
                    );

                    $toInventory->quantity += $stockTransfer->quantity;
                    $toInventory->save();
                });
            }
        });
    }

    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
