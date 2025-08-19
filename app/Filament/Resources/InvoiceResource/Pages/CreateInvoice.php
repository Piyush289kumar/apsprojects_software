<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = 'INV-' . strtoupper(\Illuminate\Support\Str::random(8));
        }

        $data['created_by'] = auth()->id();

        return $data;
    }

}
