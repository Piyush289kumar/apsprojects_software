<?php

namespace App\Filament\Widgets;

use App\Models\StoreInventory;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class InventoryStockChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Inventory Stock by Store';

    protected function getData(): array
    {
        // Group stock by store
        $storeStocks = StoreInventory::selectRaw('store_id, SUM(quantity) as total_stock')
            ->groupBy('store_id')
            ->with('store') // StoreInventory belongsTo Store
            ->get();

        $labels = $storeStocks->map(fn($inv) => $inv->store?->name ?? 'Unknown Store')->toArray();
        $data = $storeStocks->pluck('total_stock')->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Stock',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'polarArea'; // or 'doughnut', 'polarArea', etc.
    }
}
