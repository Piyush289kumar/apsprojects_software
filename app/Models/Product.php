<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'unit',
        'brand',
        'category_id',
        'hsn_code',
        'tax_slab_id',
        'gst_rate',
        'purchase_price',
        'selling_price',
        'mrp',
        'track_inventory',
        'min_stock',
        'max_stock',
        'image_path',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
        'gst_rate' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'mrp' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function taxSlab(): BelongsTo
    {
        return $this->belongsTo(TaxSlab::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(ProductVendor::class);
    }

    public function storeInventories()
    {
        return $this->hasMany(StoreInventory::class);
    }


    // In app/Models/Product.php

    // public function storeInventories()
    // {
    //     return $this->hasMany(StoreInventory::class);
    // }

}
