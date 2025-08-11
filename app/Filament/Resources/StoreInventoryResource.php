<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreInventoryResource\Pages;
use App\Filament\Resources\StoreInventoryResource\RelationManagers;
use App\Models\StoreInventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;

class StoreInventoryResource extends Resource
{
    protected static ?string $model = StoreInventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required(),

                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {
                        $storeId = $get('store_id');
                        if (!$storeId) {
                            return [];
                        }

                        return \App\Models\Product::whereDoesntHave('storeInventories', function ($query) use ($storeId) {
                            $query->where('store_id', $storeId);
                        })->pluck('name', 'id');
                    })
                    ->reactive(),

                Forms\Components\TextInput::make('quantity')->numeric()->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('product.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Filter by Store'),
            ])
            ->defaultSort('store.name')
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListStoreInventories::route('/'),
            // 'create' => Pages\CreateStoreInventory::route('/create'),
            // 'edit' => Pages\EditStoreInventory::route('/{record}/edit'),
        ];
    }
}
