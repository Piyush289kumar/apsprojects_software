<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory;

class StoreInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'quantity',
        'avg_purchase_price',
        'avg_selling_price',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::saved(function (StoreInventory $storeInventory) {
            $productId = $storeInventory->product_id;

            // Sum quantity of all stores for this product
            $totalQuantity = self::where('product_id', $productId)->sum('quantity');

            // Calculate average prices (weighted avg would be better but this is simple average)
            $avgPurchasePrice = self::where('product_id', $productId)->avg('avg_purchase_price');
            $avgSellingPrice = self::where('product_id', $productId)->avg('avg_selling_price');

            // Update or create the central inventory record
            Inventory::updateOrCreate(
                ['product_id' => $productId],
                [
                    'total_quantity' => $totalQuantity,
                    'avg_purchase_price' => $avgPurchasePrice ?: 0,
                    'avg_selling_price' => $avgSellingPrice ?: 0,
                ]
            );
        });
    }

    public static function adjustStock($storeId, $productId, $qtyChange)
    {
        $inventory = self::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->first();

        if ($inventory) {
            $inventory->quantity += $qtyChange;
            $inventory->save();
        } else {
            self::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'quantity' => $qtyChange,
            ]);
        }
    }
}
