<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\StoreInventory;
use App\Models\Inventory;
use App\Observers\StoreInventoryObserver;
use App\Observers\InventoryObserver;
use TomatoPHP\FilamentDocs\Facades\FilamentDocs;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        StoreInventory::observe(StoreInventoryObserver::class);
        Inventory::observe(InventoryObserver::class);
        // FilamentDocs::header('filament.header');
        // FilamentDocs::footer('filament.footer');
    }

}
