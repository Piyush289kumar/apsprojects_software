<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
    protected function beforeSave(): void
    {
        if (empty($this->record->invoice_number)) {
            $this->record->invoice_number = 'INV-' . strtoupper(\Illuminate\Support\Str::random(8));
        }

        $this->record->created_by = auth()->id();

        // You can add more logic here to calculate totals before saving if you want
    }
    protected function afterSave(): void
    {
        parent::afterSave();

        InvoiceResource::generateAndSaveInvoicePdf($this->record);
    }


}
