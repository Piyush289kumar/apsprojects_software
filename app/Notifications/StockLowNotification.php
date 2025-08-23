<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class StockLowNotification extends Notification
{
    use Queueable;

    protected string $productName;
    protected int $quantity;
    protected int $minStock;

    public function __construct(string $productName, int $quantity, int $minStock)
    {
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->minStock = $minStock;
    }

    /**
     * Delivery channels.
     */
    public function via($notifiable)
    {
        return ['database']; // store in Filament notifications
    }

    /**
     * Format notification for database.
     */

    public function toDatabase($notifiable)
    {
        return [
            'title' => "Stock Low: {$this->productName}",
            'body' => "Current stock ({$this->quantity}) is below minimum threshold ({$this->minStock}).",
            'url' => url('/admin/products'), // <-- replace with your actual Filament URL
        ];
    }
}
