<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseRequisitionResource\Pages;
use App\Filament\Resources\PurchaseRequisitionResource\RelationManagers;
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
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required(),

                            Textarea::make('note')->label('Note')->rows(1),
                        ])
                    ])
                    ->columnSpan('full'),
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
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => Auth::user()?->isStoreManager() && $record->status === 'pending' && $record->requested_by === Auth::id()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => Auth::user()?->isAdmin() ?? false),
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
                                TextInput::make('id')->hidden()->dehydrated(),
                                TextInput::make('product_name')->label('Product')->disabled(),
                                TextInput::make('quantity')->label('Requested')->disabled(),
                                TextInput::make('approved_quantity')->label('Approved')->numeric()->required(),
                            ])
                            ->default(fn($record) => $record->items->map(fn($i) => [
                                'id' => $i->id,
                                'product_name' => $i->product->name,
                                'quantity' => $i->quantity,
                                'approved_quantity' => $i->approved_quantity ?? $i->quantity,
                            ]))
                            ->columns(1),
                    ])
                    ->action(function (PurchaseRequisition $record, array $data) {
                        foreach ($record->items as $index => $item) {
                            $approvedQuantity = $data['items'][$index]['approved_quantity'] ?? null;
                            if ($approvedQuantity !== null) {
                                $item->approved_quantity = (int) $approvedQuantity;
                                $item->save();
                            }
                        }

                        if ($data['method'] === 'purchase') {
                            $vendorId = $data['vendor_id'] ?? null;
                            $po = PurchaseOrder::createFromRequisition($record, $vendorId, Auth::user());
                            $record->status = 'approved';
                            $record->approved_by = Auth::id();
                            $record->approved_at = now();
                            $record->save();
                        } elseif ($data['method'] === 'transfer') {
                            $fromStoreId = $data['source_store_id'] ?? null;
                            $transfer = TransferOrder::createFromRequisition($record, $fromStoreId, Auth::user());
                            $record->status = 'approved';
                            $record->approved_by = Auth::id();
                            $record->approved_at = now();
                            $record->save();
                        }
                    }),
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
