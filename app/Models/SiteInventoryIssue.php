<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteInventoryIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'site_id',
        'product_id',
        'issued_by',
        'quantity',        
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // Relations
    public function store() {
        return $this->belongsTo(Store::class);
    }

    public function site() {
        return $this->belongsTo(Site::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function issuer() {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
