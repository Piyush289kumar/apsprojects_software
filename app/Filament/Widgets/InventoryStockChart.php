<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class InventoryStockChart extends ChartWidget
{
    use HasWidgetShield;    
    protected static ?string $heading = 'Inventory Stock Levels';
    protected function getData(): array
    {
        // Get product names and quantities from inventories
        $inventories = Inventory::with('product')->get();

        $labels = $inventories->pluck('product.name')->toArray();
        $data = $inventories->pluck('total_quantity')->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Stock Quantity',
                    'data' => $data,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}
