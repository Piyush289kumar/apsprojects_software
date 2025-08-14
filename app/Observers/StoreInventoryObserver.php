<?php

namespace App\Observers;

use App\Models\StoreInventory;
use App\Services\InventoryLogger;

class StoreInventoryObserver
{
    public function created(StoreInventory $inventory): void
    {
        $inventory->loadMissing(['store', 'product']);

        if ((int) $inventory->quantity > 0) {
            InventoryLogger::log([
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'type' => 'adjustment_in',
                'quantity' => $inventory->quantity,
                'remarks' => sprintf(
                    'Initial store stock created for "%s", Product "%s", with QTY: %d',
                    $inventory->store->name ?? 'Unknown Store',
                    $inventory->product->name ?? 'Unknown Product',
                    $inventory->quantity
                ),
            ]);

            request()->merge(['skip_central_log' => true]);
        }
    }

    public function updated(StoreInventory $inventory): void
    {
        $inventory->loadMissing(['store', 'product']);

        if ($inventory->wasChanged('quantity')) {
            $old = (int) $inventory->getOriginal('quantity');
            $new = (int) $inventory->quantity;
            $diff = $new - $old;

            if ($diff !== 0) {
                InventoryLogger::log([
                    'store_id' => $inventory->store_id,
                    'product_id' => $inventory->product_id,
                    'type' => $diff > 0 ? 'adjustment_in' : 'adjustment_out',
                    'quantity' => abs($diff),
                    'remarks' => sprintf(
                        'Store stock updated for "%s", Product "%s", old QTY: %d → new QTY: %d (change: %s%d)',
                        $inventory->store->name ?? 'Unknown Store',
                        $inventory->product->name ?? 'Unknown Product',
                        $old,
                        $new,
                        $diff > 0 ? '+' : '',
                        $diff
                    ),
                ]);

                request()->merge(['skip_central_log' => true]);
            }
        }
    }

    public function deleted(StoreInventory $inventory): void
    {
        $inventory->loadMissing(['store', 'product']);

        $qty = (int) $inventory->getOriginal('quantity');
        if ($qty > 0) {
            InventoryLogger::log([
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'type' => 'adjustment_out',
                'quantity' => $qty,
                'remarks' => sprintf(
                    'Store stock deleted for "%s", Product "%s" — remaining QTY: %d removed',
                    $inventory->store->name ?? 'Unknown Store',
                    $inventory->product->name ?? 'Unknown Product',
                    $qty
                ),
            ]);

            request()->merge(['skip_central_log' => true]);
        }
    }
}
