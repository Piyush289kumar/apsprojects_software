<?php
namespace App\Filament\Resources;
use App\Filament\Resources\SiteInventoryIssueResource\Pages;
use App\Filament\Resources\SiteInventoryIssueResource\RelationManagers;
use App\Models\SiteInventoryIssue;
use Auth;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
class SiteInventoryIssueResource extends Resource
{
    protected static ?string $model = SiteInventoryIssue::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Stores';
    protected static ?string $navigationLabel = 'Site Inventory Issues';
    protected static ?int $navigationSort = 8;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Store + Site + Issued By + Notes
                Grid::make(3)->schema([
                    Select::make('store_id')
                        ->label('Store')
                        ->relationship('store', 'name')
                        ->required()
                        ->default(fn() => Auth::user()->role !== 'admin' ? Auth::user()->store_id : null)
                        ->disabled(fn() => Auth::user()->role !== 'admin')
                        ->reactive(),

                    Select::make('site_id')
                        ->label('Site')
                        ->required()
                        ->reactive()
                        ->options(fn(callable $get) => $get('store_id')
                            ? \App\Models\Site::where('store_id', $get('store_id'))->pluck('name', 'id')->toArray()
                            : []),

                    Select::make('issued_by')
                        ->label('Issued By')
                        ->relationship('issuer', 'name')
                        ->required()
                        ->default(fn() => Auth::user()->role !== 'admin' ? Auth::user()->id : null)
                        ->disabled(fn() => Auth::user()->role !== 'admin'),
                ]),

                // Repeater for multiple products
                Repeater::make('products')
                    ->label('Products to Issue')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            Textarea::make('notes')
                                ->label('Notes')
                                ->rows(1),
                        ]),
                    ])
                    ->columns(1)
                    ->columnSpan('full')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label('Store')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('site.name')->label('Site')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('issuer.name')->label('Issued By')->sortable(),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Issued On')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'issued' => 'Issued',
                    'returned' => 'Returned',
                    'damaged' => 'Damaged',
                ]),
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
            'index' => Pages\ListSiteInventoryIssues::route('/'),
            // 'create' => Pages\CreateSiteInventoryIssue::route('/create'),
            // 'edit' => Pages\EditSiteInventoryIssue::route('/{record}/edit'),
        ];
    }
}
