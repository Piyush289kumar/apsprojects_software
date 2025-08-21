<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class TransferOrder extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'transfer_number',
        'from_store_id',
        'to_store_id',
        'created_by',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(TransferOrderItem::class);
    }

    /**
     * Create Transfer Order from Purchase Requisition
     */
    public static function createFromRequisition(PurchaseRequisition $req, $fromStoreId, $creator)
    {
        $t = self::create([
            'transfer_number' => 'TR-' . strtoupper(Str::random(8)),
            'from_store_id' => $fromStoreId,
            'to_store_id' => $req->store_id,
            'created_by' => $creator->id,
            'status' => 'completed', // mark as completed for instant stock movement
        ]);

        foreach ($req->items as $item) {
            $qty = $item->approved_quantity ?? $item->quantity;

            if ($qty > 0) {
                // Create transfer order item
                $t->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                ]);

                // Update inventory
                StoreInventory::adjustStock($fromStoreId, $item->product_id, -$qty);
                StoreInventory::adjustStock($req->store_id, $item->product_id, $qty);
            }
        }

        return $t;
    }

}
