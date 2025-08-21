<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Spatie\Permission\Traits\HasRoles;

class SiteInventoryIssueItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'site_inventory_issue_id',
        'product_id',
        'quantity',
        'notes',
    ];

    protected $oldQuantity = null;

    public function issue()
    {
        return $this->belongsTo(SiteInventoryIssue::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}
