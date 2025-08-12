<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 5;

    public static function afterCreate(array $data, Invoice $record)
    {
        self::generateInvoicePdf($record);
    }

    public static function afterUpdate(array $data, Invoice $record)
    {
        self::generateInvoicePdf($record);
    }

    protected static function generateInvoicePdf(Invoice $invoice): void
    {
        $pdf = PDF::loadView('invoice_pdf', ['invoice' => $invoice]);

        $pdfContent = $pdf->output();

        Storage::disk('public')->put('invoices/invoice-' . $invoice->invoice_number . '.pdf', $pdfContent);
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->required()
                            ->readonly()
                            ->default(fn() => 'INV-' . strtoupper(Str::random(8)))
                            ->unique(ignoreRecord: true),

                        Select::make('billable_type')
                            ->label('Bill To')
                            ->options([
                                'App\Models\Customer' => 'Customer',
                                'App\Models\Vendor' => 'Vendor',
                            ])
                            ->required()
                            ->reactive(),

                        Select::make('billable_id')
                            ->label('Select Customer/Vendor')
                            ->options(function (callable $get) {
                                $type = $get('billable_type');
                                if (!$type) {
                                    return [];
                                }
                                return $type::query()->pluck('name', 'id')->toArray();
                            })
                            ->required()
                            ->reactive(),
                    ]),

                Grid::make('4')
                    ->schema([
                        Select::make('type')
                            ->label('Invoice Type')
                            ->options([
                                'sale' => 'Sale',
                                'purchase' => 'Purchase',
                            ])
                            ->required()
                            ->default('sale'),

                        DatePicker::make('invoice_date')
                            ->label('Invoice Date')
                            ->required()
                            ->default(now()),

                        DatePicker::make('due_date')
                            ->label('Due Date'),

                        TextInput::make('place_of_supply')
                            ->label('Place of Supply (State Code)')
                            ->maxLength(5),
                    ]),

                Grid::make('1')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('Invoice Items')
                            ->required()
                            ->reactive()
                            // Recalculate invoice totals only once when entire items array updates
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                InvoiceResource::recalculateInvoiceTotals($set, $get);
                            })
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->options(Product::pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, $get, $state) {
                                                if ($state) {
                                                    $product = Product::with('taxSlab')->find($state);
                                                    if ($product) {
                                                        $set('unit_price', $product->selling_price);
                                                        $gstRate = $product->taxSlab ? $product->taxSlab->rate : 0;
                                                        $gstHalf = $gstRate / 2;

                                                        $set('cgst_rate', $gstHalf);
                                                        $set('sgst_rate', $gstHalf);
                                                        $set('igst_rate', 0);

                                                        InvoiceResource::recalculateItem($set, $get);
                                                    }
                                                }
                                            })
                                            ->columnSpan(3),

                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                InvoiceResource::recalculateItem($set, $get);
                                                // InvoiceResource::recalculateInvoiceTotals($set, $get);
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                InvoiceResource::recalculateItem($set, $get);
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('cgst_rate')
                                            ->label('CGST Rate (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                InvoiceResource::recalculateItem($set, $get);
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('sgst_rate')
                                            ->label('SGST Rate (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                InvoiceResource::recalculateItem($set, $get);
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('igst_rate')
                                            ->label('IGST Rate (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                InvoiceResource::recalculateItem($set, $get);
                                            })
                                            ->columnSpan(1),

                                        TextInput::make('cgst_amount')->label('CGST Amount')->numeric()->disabled()->columnSpan(1),
                                        TextInput::make('sgst_amount')->label('SGST Amount')->numeric()->disabled()->columnSpan(1),
                                        TextInput::make('igst_amount')->label('IGST Amount')->numeric()->disabled()->columnSpan(1),
                                        TextInput::make('total_amount')->label('Total Amount')->numeric()->disabled()->columnSpan(2),
                                    ]),
                            ]),
                    ]),

                TextInput::make('taxable_value')
                    ->label('Taxable Value')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->default(0),

                TextInput::make('cgst_amount')
                    ->label('CGST Amount')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->default(0),

                TextInput::make('sgst_amount')
                    ->label('SGST Amount')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->default(0),

                TextInput::make('igst_amount')
                    ->label('IGST Amount')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->default(0),

                TextInput::make('total_tax')
                    ->label('Total Tax')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->default(0),

                TextInput::make('discount')
                    ->label('Invoice Discount')
                    ->numeric()
                    ->default(0)
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                        // only recalc totals here, no item recalcs
                        InvoiceResource::recalculateInvoiceTotals($set, $get);
                    }),

                TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(true)
                    ->reactive()
                    ->default(0),

                Forms\Components\Textarea::make('notes')->label('Additional Notes')->rows(3),

                Select::make('status')->label('Payment Status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'partial' => 'Partial',
                    'cancelled' => 'Cancelled',
                ])->default('pending')->required(),
            ]);
    }

    public static function recalculateInvoiceTotals(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];

        $taxableValue = 0;
        $cgstAmount = 0;
        $sgstAmount = 0;
        $igstAmount = 0;
        $totalAmount = 0;

        foreach ($items as $item) {
            $taxableValue += (($item['unit_price'] ?? 0.0) * ($item['quantity'] ?? 0.0)) - ($item['discount'] ?? 0.0);
            $cgstAmount += $item['cgst_amount'] ?? 0;
            $sgstAmount += $item['sgst_amount'] ?? 0;
            $igstAmount += $item['igst_amount'] ?? 0;
            $totalAmount += $item['total_amount'] ?? 0;
        }

        $totalTax = $cgstAmount + $sgstAmount + $igstAmount;
        $invoiceDiscount = $get('discount') ?? 0;
        $totalAmountAfterDiscount = $totalAmount - $invoiceDiscount;

        $set('taxable_value', round($taxableValue, 2));
        $set('cgst_amount', round($cgstAmount, 2));
        $set('sgst_amount', round($sgstAmount, 2));
        $set('igst_amount', round($igstAmount, 2));
        $set('total_tax', round($totalTax, 2));
        $set('total_amount', round($totalAmountAfterDiscount, 2));
    }

    public static function recalculateItem(callable $set, callable $get): void
    {
        $quantity = $get('quantity') ?? 1;
        $unitPrice = $get('unit_price') ?? 0;
        $discount = $get('discount') ?? 0;
        $cgstRate = $get('cgst_rate') ?? 0;
        $sgstRate = $get('sgst_rate') ?? 0;
        $igstRate = $get('igst_rate') ?? 0;

        $taxable = ($unitPrice * $quantity) - $discount;

        $cgstAmount = ($taxable * $cgstRate) / 100;
        $sgstAmount = ($taxable * $sgstRate) / 100;
        $igstAmount = ($taxable * $igstRate) / 100;

        $totalAmount = $taxable + $cgstAmount + $sgstAmount + $igstAmount;

        $set('cgst_amount', round($cgstAmount, 2));
        $set('sgst_amount', round($sgstAmount, 2));
        $set('igst_amount', round($igstAmount, 2));
        $set('total_amount', round($totalAmount, 2));
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $taxableValue = 0;
        $cgstAmount = 0;
        $sgstAmount = 0;
        $igstAmount = 0;
        $totalAmount = 0;

        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                $quantity = $item['quantity'] ?? 0;
                $unitPrice = $item['unit_price'] ?? 0;
                $discount = $item['discount'] ?? 0;
                $cgstRate = $item['cgst_rate'] ?? 0;
                $sgstRate = $item['sgst_rate'] ?? 0;
                $igstRate = $item['igst_rate'] ?? 0;

                $taxable = ($unitPrice * $quantity) - $discount;

                $itemCgstAmount = ($taxable * $cgstRate) / 100;
                $itemSgstAmount = ($taxable * $sgstRate) / 100;
                $itemIgstAmount = ($taxable * $igstRate) / 100;
                $itemTotalAmount = $taxable + $itemCgstAmount + $itemSgstAmount + $itemIgstAmount;

                $item['cgst_amount'] = round($itemCgstAmount, 2);
                $item['sgst_amount'] = round($itemSgstAmount, 2);
                $item['igst_amount'] = round($itemIgstAmount, 2);
                $item['total_amount'] = round($itemTotalAmount, 2);

                $taxableValue += $taxable;
                $cgstAmount += $itemCgstAmount;
                $sgstAmount += $itemSgstAmount;
                $igstAmount += $itemIgstAmount;
                $totalAmount += $itemTotalAmount;
            }
            unset($item);
        }

        $totalTax = $cgstAmount + $sgstAmount + $igstAmount;
        $discount = $data['discount'] ?? 0;
        $totalAmountAfterDiscount = $totalAmount - $discount;

        return array_merge($data, [
            'items' => $data['items'],
            'taxable_value' => round($taxableValue, 2),
            'cgst_amount' => round($cgstAmount, 2),
            'sgst_amount' => round($sgstAmount, 2),
            'igst_amount' => round($igstAmount, 2),
            'total_tax' => round($totalTax, 2),
            'total_amount' => round($totalAmountAfterDiscount, 2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('billable.name')->label('Billed To')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type')->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Action::make('view_pdf')
                    ->label('View PDF')
                    ->icon('heroicon-o-document-download')
                    ->url(fn($record) => asset('storage/invoices/invoice-' . $record->invoice_number . '.pdf'))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => Storage::disk('public')->exists('invoices/invoice-' . $record->invoice_number . '.pdf')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
