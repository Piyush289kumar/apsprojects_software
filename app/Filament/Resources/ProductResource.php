<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('sku')->required()->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('barcode')->maxLength(255),
                        Forms\Components\TextInput::make('unit')->maxLength(50),
                        Forms\Components\TextInput::make('brand')->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Category & Tax')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->nullable(),
                        Forms\Components\TextInput::make('hsn_code')->maxLength(8),
                        Forms\Components\Select::make('tax_slab_id')
                            ->relationship('taxSlab', 'name')
                            ->nullable(),
                        Forms\Components\TextInput::make('gst_rate')->numeric()->step(0.01)->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('purchase_price')->numeric()->step(0.01)->required(),
                        Forms\Components\TextInput::make('selling_price')->numeric()->step(0.01)->required(),
                        Forms\Components\TextInput::make('mrp')->numeric()->step(0.01)->nullable(),
                    ])->columns(3),

                Forms\Components\Section::make('Stock Management')
                    ->schema([
                        Forms\Components\Toggle::make('track_inventory')->default(true),
                        Forms\Components\TextInput::make('min_stock')->numeric()->default(0),
                        Forms\Components\TextInput::make('max_stock')->numeric()->nullable(),
                    ])->columns(3),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->disk('public')
                            ->directory('products')
                            ->image()
                            ->nullable(),
                        Forms\Components\Toggle::make('is_active')->default(true),
                        Forms\Components\KeyValue::make('meta')
                            ->label('Custom Metadata')
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->disk('public')->square(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('sku')->searchable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category'),
                Tables\Columns\TextColumn::make('selling_price')->money('INR'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->date(),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
