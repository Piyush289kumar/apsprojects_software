<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'slug',
        'description',
        'image_path',
        'parent_id',
        'hsn_code',
        'default_gst_rate',
        'tax_slab_id',
        'track_inventory',
        'default_min_stock',
        'default_max_stock',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'track_inventory' => 'boolean',
        'is_active'       => 'boolean',
        'meta'            => 'array',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function taxSlab()
    {
        return $this->belongsTo(TaxSlab::class);
    }
}
