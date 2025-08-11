<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVendor extends Model
{
    protected $fillable = [
        'product_id',
        'vendor_id',
        'last_purchase_price',
        'average_purchase_price',
        'last_purchase_date',
    ];

    protected $casts = [
        'last_purchase_price' => 'decimal:2',
        'average_purchase_price' => 'decimal:2',
        'last_purchase_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
