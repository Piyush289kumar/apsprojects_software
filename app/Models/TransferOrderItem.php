<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_order_id',
        'product_id',
        'quantity',
        'transferred_quantity',
    ];

    public function transferOrder()
    {
        return $this->belongsTo(TransferOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
