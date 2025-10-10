<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteInventoryIssueResource\Pages;
use App\Models\SiteInventoryIssue;
use App\Models\Store;
use App\Models\Site;
use App\Models\StoreInventory;
use Auth;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Illuminate\Database\Eloquent\Builder;

class SiteInventoryIssueResource extends Resource
{
    protected static ?string $model = SiteInventoryIssue::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Stores';
    protected static ?string $navigationLabel = 'Stock Out';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(3)->schema([
                // Action Type
                Select::make('issue_type')
                    ->label('Action Type')
                    ->options([
                        'site' => 'Issue to Site',
                        'transfer' => 'Transfer to Another Store',
                    ])
                    ->required()
                    ->live() // 👈 ensures full reactivity
                    ->default('site'),
                // Store (From)
                Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn() => Auth::user()?->isStoreManager() ? Auth::user()->store_id : null)
                    ->disabled(fn() => Auth::user()?->isStoreManager())
                    ->dehydrated(),

                // Site (Visible only if issue_type = site)
                Select::make('site_id')
                    ->label('Site')
                    ->required(fn(callable $get) => $get('issue_type') === 'site')
                    ->visible(fn(callable $get) => $get('issue_type') === 'site')
                    ->searchable()
                    ->preload()
                    ->options(fn(callable $get) => Site::where('store_id', $get('store_id'))->pluck('name', 'id')->toArray()),

                // Transfer To Store (Visible only if issue_type = transfer)
                Select::make('transfer_to_store_id')
                    ->label('Transfer To Store')
                    ->options(function (callable $get) {
                        $storeId = $get('store_id');
                        if (!$storeId) {
                            return [];
                        }

                        // Exclude current store from transfer list
                        return Store::where('id', '!=', $storeId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->visible(fn(callable $get) => $get('issue_type') === 'transfer')
                    ->dehydrated(fn(callable $get) => $get('issue_type') === 'transfer') // 👈 important
                    ->required(fn(callable $get) => $get('issue_type') === 'transfer')
                    ->searchable()
                    ->preload(),
                // Issued By
                Select::make('issued_by')
                    ->label('Issued By')
                    ->relationship('issuer', 'name')
                    ->required()
                    ->default(fn() => Auth::id())
                    ->disabled(fn() => !Auth::user()?->isAdmin())
                    ->dehydrated(),
            ]),

            // Items Section
            Grid::make(1)->schema([
                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->reactive(),

                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->maxValue(function (callable $get) {
                                $storeId = $get('../../store_id');
                                $productId = $get('product_id');

                                if (!$storeId || !$productId) {
                                    return null;
                                }

                                $inventory = StoreInventory::where('store_id', $storeId)
                                    ->where('product_id', $productId)
                                    ->first();

                                return $inventory?->quantity ?? 0;
                            })
                            ->helperText(function (callable $get) {
                                $storeId = $get('../../store_id');
                                $productId = $get('product_id');
                                if (!$storeId || !$productId) {
                                    return null;
                                }

                                $inventory = StoreInventory::where('store_id', $storeId)
                                    ->where('product_id', $productId)
                                    ->first();

                                return 'Available stock: ' . ($inventory?->quantity ?? 0);
                            }),

                        Textarea::make('notes')->rows(1),
                    ])
                    ->columns(3)
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('issue_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'site',
                        'success' => 'transfer',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site')
                    ->sortable()
                    ->searchable()
                    ->default('-'),

                Tables\Columns\TextColumn::make('transferToStore.name')
                    ->label('Transfer To')
                    ->sortable()
                    ->searchable()
                    ->default('-'),

                Tables\Columns\TextColumn::make('issuer.name')
                    ->label('Issued By')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Products Count'),

                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->badge()
                    ->colors([
                        'success' => 'issued',
                        'warning' => 'returned',
                        'danger' => 'damaged',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Issued On')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'issued' => 'Issued',
                    'returned' => 'Returned',
                    'damaged' => 'Damaged',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteInventoryIssues::route('/'),
        ];
    }

    // public static function getEloquentQuery(): Builder
    // {
    //     $query = parent::getEloquentQuery();
    //     $user = Auth::user();

    //     if ($user && $user->isStoreManager()) {
    //         $query->where('store_id', $user->store_id);
    //     }

    //     return $query;
    // }




    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['store', 'site', 'transferToStore', 'issuer']);

        $user = Auth::user();
        if ($user && $user->isStoreManager()) {
            $query->where('store_id', $user->store_id);
        }

        return $query;
    }

}
