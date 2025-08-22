<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseRequisitionResource\Pages;
use App\Filament\Resources\PurchaseRequisitionResource\RelationManagers;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\Store;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use App\Models\PurchaseOrder;
use App\Models\TransferOrder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class PurchaseRequisitionResource extends Resource
{
    protected static ?string $model = PurchaseRequisition::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Purchase';
    protected static ?int $navigationSort = 2;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    Select::make('store_id')
                        ->label('Store (Requesting)')
                        ->options(Store::pluck('name', 'id'))
                        ->default(fn() => Auth::user()->store_id ?? null)
                        ->required()
                        ->disabled(fn() => Auth::user()?->isStoreManager() ?? false)
                        ->dehydrated(true),

                    TextInput::make('reference')->label('Reference'),
                    Select::make('priority')
                        ->label('Priority')
                        ->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                        ])
                        ->default('medium'),

                    Textarea::make('notes')->label('Notes')->rows(1)->columnSpan('full'),
                ]),

                Repeater::make('items')
                    ->relationship('items')
                    ->label('Requested Items')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->options(Product::pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->required()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('purchase_price', $product->purchase_price);
                                        }
                                    }
                                }),

                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $set('total_price', $state * ($get('purchase_price') ?? 0));
                                }),

                            TextInput::make('purchase_price')
                                ->label('Purchase Unit Price')
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->reactive()
                                ->default(0)
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $set('total_price', $state * ($get('quantity') ?? 0));
                                }),

                            Grid::make(2)->schema([
                                TextInput::make('total_price')
                                    ->label('Total Price')
                                    ->numeric()
                                    ->disabled()
                                    ->default(0)
                                    ->dehydrated(true) // 👈 important: store in DB
                                    ->required(),

                                Textarea::make('note')->label('Note')->rows(1),
                            ])
                        ])
                    ])
                    ->columnSpan('full')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Show only pending by default
                $query->where('status', 'pending');
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('store.name')->label('Store')->sortable(),
                Tables\Columns\TextColumn::make('requester.name')->label('Requested By'),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),


                Action::make('approve')
                    ->label('Approve')
                    ->modalHeading('Approve / Fulfill Requisition')
                    ->visible(fn($record) => Auth::user()?->isAdmin() ?? false)
                    ->form([
                        Select::make('method')
                            ->label('Fulfillment Method')
                            ->options([
                                'purchase' => 'Purchase from Vendor',
                                'transfer' => 'Transfer from Another Store',
                            ])
                            ->reactive()
                            ->required(),

                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->options(Vendor::pluck('name', 'id'))
                            ->visible(fn($get) => $get('method') === 'purchase'),

                        Select::make('source_store_id')
                            ->label('Source Store')
                            ->options(Store::pluck('name', 'id'))
                            ->visible(fn($get) => $get('method') === 'transfer'),

                        Repeater::make('items')
                            ->label('Approve quantities (per item)')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextInput::make('id')->hidden()->dehydrated(),

                                    TextInput::make('product_name')
                                        ->label('Product')
                                        ->disabled(),

                                    TextInput::make('quantity')
                                        ->label('Requested Qty')
                                        ->disabled(),

                                    TextInput::make('purchase_price')
                                        ->label('Requested Price')
                                        ->disabled(),

                                    TextInput::make('requested_total')
                                        ->label('Requested Total')
                                        ->disabled()
                                        ->afterStateHydrated(
                                            fn($state, $get, $set) =>
                                            $set('requested_total', ($get('quantity') ?? 0) * ($get('purchase_price') ?? 0))
                                        ),

                                    TextInput::make('approved_quantity')
                                        ->label('Approved Qty')
                                        ->numeric()
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                            $set('approved_total', $state * ($get('approved_price') ?? 0));
                                        }),

                                    TextInput::make('approved_price')
                                        ->label('Approved Price')
                                        ->numeric()
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                            $set('approved_total', ($get('approved_quantity') ?? 0) * $state);
                                        }),

                                    TextInput::make('approved_total')
                                        ->label('Approved Total')
                                        ->disabled()
                                        ->dehydrated(true),
                                ])
                            ])
                            ->default(fn($record) => $record?->items?->map(fn($i) => [
                                'id' => $i->id,
                                'product_name' => $i->product->name,
                                'quantity' => $i->quantity,
                                'purchase_price' => $i->purchase_price,
                                'requested_total' => $i->quantity * $i->purchase_price,
                                'approved_quantity' => $i->approved_quantity ?? $i->quantity,
                                'approved_price' => $i->approved_price ?? $i->purchase_price,
                                'approved_total' => ($i->approved_quantity ?? $i->quantity) * ($i->approved_price ?? $i->purchase_price),
                            ])->toArray() ?? [])
                            ->columns('full'),
                    ])
                    ->action(function (PurchaseRequisition $record, array $data) {
                        // 1. Save approved quantities
                        foreach ($record->items as $index => $item) {
                            $approvedQuantity = $data['items'][$index]['approved_quantity'] ?? null;
                            $approvedPrice = $data['items'][$index]['approved_price'] ?? $item->purchase_price ?? 0;

                            if ($approvedQuantity !== null) {
                                $item->approved_quantity = (int) $approvedQuantity;
                                $item->approved_price = $approvedPrice;
                                $item->total_price = $approvedQuantity * $approvedPrice;
                                $item->save();
                            }
                        }

                        $invoice = null;

                        // 2. Handle Purchase or Transfer & create Invoice
                        if ($data['method'] === 'purchase') {
                            $vendorId = $data['vendor_id'] ?? null;

                            PurchaseOrder::createFromRequisition($record, $vendorId, Auth::user());

                            $invoice = Invoice::create([
                                'document_type' => 'purchase_order',
                                'billable_id' => $vendorId,
                                'billable_type' => Vendor::class,
                                'document_date' => now(),
                                'status' => 'draft',
                                'notes' => $record->notes,
                                'created_by' => Auth::id(),
                            ]);

                        } elseif ($data['method'] === 'transfer') {
                            $fromStoreId = $data['source_store_id'] ?? null;                            

                            $invoice = Invoice::create([
                                'document_type' => 'transfer_order',
                                'billable_id' => $fromStoreId,
                                'billable_type' => Store::class,
                                'document_date' => now(),
                                'status' => 'draft',
                                'notes' => $record->notes,
                                'created_by' => Auth::id(),
                            ]);
                        }

                        // 3. Add Invoice Items
                        if ($invoice) {
                            $items = [];
                            $total = 0;

                            foreach ($record->items as $item) {
                                $unitPrice = $item->approved_price ?? $item->purchase_price ?? 0;
                                $lineTotal = $item->total_price ?? ($unitPrice * $item->approved_quantity);
                                $total += $lineTotal;

                                $items[] = [
                                    'product_id' => $item->product_id,
                                    'description' => $item->product->name,
                                    'quantity' => $item->approved_quantity,
                                    'unit_price' => $unitPrice,
                                    'total_amount' => $lineTotal, // ✅ Correct field
                                ];
                            }

                            $invoice->items()->createMany($items);
                            $invoice->update(['total_amount' => $total]);
                        }

                        // 4. Mark requisition approved
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    })

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
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
            'index' => Pages\ListPurchaseRequisitions::route('/'),
            // 'create' => Pages\CreatePurchaseRequisition::route('/create'),
            // 'edit' => Pages\EditPurchaseRequisition::route('/{record}/edit'),
        ];
    }
}
