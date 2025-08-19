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
    public static function generateInvoicePdf(Invoice $invoice): void
    {
        try {

            // 4️⃣ Create PDF invoice
            $pdf = Pdf::loadView('pdf.invoice', ['record' => $invoice]);
            $fileName = "invoices/invoice-{ $invoice->invoice_number }.pdf";
            Storage::disk('public')->put($fileName, $pdf->output());

            $invoice->update(['invoice_path' => $fileName]);

        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
        }
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
                            ->default('purchase'),
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
                                        Grid::make(12)
                                            ->schema([
                                                Select::make('product_id')
                                                    ->label('Product')
                                                    ->options(Product::pluck('name', 'id'))
                                                    ->searchable()
                                                    ->required()
                                                    ->reactive()
                                                    ->createOptionForm([
                                                        Grid::make(2)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('name')
                                                                    ->label('Product Name')
                                                                    ->placeholder('Enter product name') // <-- placeholder added
                                                                    ->required(),
                                                                Forms\Components\TextInput::make('selling_price')
                                                                    ->label('Selling Price')
                                                                    ->placeholder('Enter selling price') // <-- placeholder added
                                                                    ->numeric()
                                                                    ->default(0)
                                                                    ->required(),
                                                            ]),
                                                    ])
                                                    ->createOptionUsing(function (array $data) {
                                                        $sku = 'PRD-' . str_pad(Product::max('id') + 1, 5, '0', STR_PAD_LEFT);
                                                        $product = Product::create([
                                                            'name' => $data['name'],
                                                            'selling_price' => $data['selling_price'] ?? 0,
                                                            'is_active' => false,
                                                            'sku' => $sku,
                                                            'purchase_price' => 0,
                                                            'track_inventory' => false,
                                                        ]);
                                                        return $product->id;
                                                    })
                                                    ->afterStateUpdated(function (callable $set, $get, $state) {
                                                        if ($state) {
                                                            $product = Product::find($state);
                                                            if ($product) {
                                                                $set('unit_price', $product->selling_price);
                                                                // Set GST rates to 0 as tax slab is removed
                                                                $set('cgst_rate', 0);
                                                                $set('sgst_rate', 0);
                                                                $set('igst_rate', 0);
                                                                InvoiceResource::recalculateItem($set, $get);
                                                            }
                                                        }
                                                    })
                                                    ->columnSpan(5),
                                                TextInput::make('quantity')
                                                    ->label('Quantity')
                                                    ->numeric()
                                                    ->required()
                                                    ->default(0) // ensures it starts at 0
                                                    ->placeholder('0') // optional, shows 0 when empty
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(2),
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
                                                    ->label('CGST (%)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(1),
                                                TextInput::make('sgst_rate')
                                                    ->label('SGST (%)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(1),
                                                TextInput::make('igst_rate')
                                                    ->label('IGST (%)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                                        InvoiceResource::recalculateItem($set, $get);
                                                    })
                                                    ->columnSpan(1),
                                            ]),
                                        Grid::make(12)
                                            ->schema([
                                                TextInput::make('cgst_amount')->label('CGST Amount')->numeric()->disabled()->columnSpan(3),
                                                TextInput::make('sgst_amount')->label('SGST Amount')->numeric()->disabled()->columnSpan(3),
                                                TextInput::make('igst_amount')->label('IGST Amount')->numeric()->disabled()->columnSpan(3),
                                                TextInput::make('total_amount')->label('Total Amount')->numeric()->disabled()->columnSpan(3),
                                            ]),
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
        foreach ($items as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $cgstRate = (float) ($item['cgst_rate'] ?? 0);
            $sgstRate = (float) ($item['sgst_rate'] ?? 0);
            $igstRate = (float) ($item['igst_rate'] ?? 0);
            $taxable = ($unitPrice * $quantity) - $discount;
            $itemCgstAmount = ($taxable * $cgstRate) / 100;
            $itemSgstAmount = ($taxable * $sgstRate) / 100;
            $itemIgstAmount = ($taxable * $igstRate) / 100;
            $itemTotalAmount = $taxable + $itemCgstAmount + $itemSgstAmount + $itemIgstAmount;
            // Update item amounts in the repeater
            $items[$index]['cgst_amount'] = round($itemCgstAmount, 2);
            $items[$index]['sgst_amount'] = round($itemSgstAmount, 2);
            $items[$index]['igst_amount'] = round($itemIgstAmount, 2);
            $items[$index]['total_amount'] = round($itemTotalAmount, 2);
            // Sum totals
            $taxableValue += $taxable;
            $cgstAmount += $itemCgstAmount;
            $sgstAmount += $itemSgstAmount;
            $igstAmount += $itemIgstAmount;
            $totalAmount += $itemTotalAmount;
        }
        $totalTax = $cgstAmount + $sgstAmount + $igstAmount;
        $invoiceDiscount = $get('discount') ?? 0;
        $totalAmountAfterDiscount = $totalAmount - $invoiceDiscount;
        // Update form values
        $set('items', $items); // <-- make sure each item's total updates
        $set('taxable_value', round($taxableValue, 2));
        $set('cgst_amount', round($cgstAmount, 2));
        $set('sgst_amount', round($sgstAmount, 2));
        $set('igst_amount', round($igstAmount, 2));
        $set('total_tax', round($totalTax, 2));
        $set('total_amount', round($totalAmountAfterDiscount, 2));
    }
    public static function recalculateItem(callable $set, callable $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discount = (float) ($get('discount') ?? 0);
        $cgstRate = (float) ($get('cgst_rate') ?? 0);
        $sgstRate = (float) ($get('sgst_rate') ?? 0);
        $igstRate = (float) ($get('igst_rate') ?? 0);
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
            ])->defaultSort('created_at', 'desc')
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
