<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'vendor_id',
        'store_id',
        'created_by',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public static function createFromRequisition(PurchaseRequisition $req, $vendorId, $creator)
    {
        $po = self::create([
            'po_number' => 'PO-' . strtoupper(Str::random(8)),
            'vendor_id' => $vendorId,
            'store_id' => $req->store_id,
            'created_by' => $creator->id,
            'status' => 'draft',
        ]);

        foreach ($req->items as $item) {
            $qty = $item->approved_quantity ?? $item->quantity;
            if ($qty > 0) {
                $po->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'unit_price' => $item->product->purchase_price ?? 0,
                ]);
            }
        }

        return $po;
    }

    
}
