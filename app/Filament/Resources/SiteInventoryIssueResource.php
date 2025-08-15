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
                Grid::make(3)->schema([
                    Forms\Components\Select::make('store_id')
                        ->label('Store')
                        ->relationship('store', 'name')
                        ->required(),

                    Forms\Components\Select::make('site_id')
                        ->label('Site')
                        ->relationship('site', 'name')
                        ->required(),

                    Forms\Components\Select::make('product_id')
                        ->label('Product')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->required(),
                ]),
                Grid::make(3)->schema([
                    Forms\Components\Select::make('issued_by')
                        ->label('Issued By')
                        ->relationship('issuer', 'name')
                        ->required()
                        ->default(function () {
                            $user = Auth::user(); // Get currently logged-in user
                            // If user is manager, auto-set their ID
                            return $user->role !== 'admin' ? $user->id : null;
                        })
                        ->disabled(function () {
                            $user = Auth::user();
                            // Disable field for managers, allow admins to select
                            return $user->role !== 'admin';
                        }),

                    Forms\Components\TextInput::make('quantity')->numeric()->required(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'issued' => 'Issued',
                            'returned' => 'Returned',
                            'damaged' => 'Damaged',
                        ])
                        ->default('issued'),
                ]),

                Forms\Components\Textarea::make('notes')->label('Notes'),
                Forms\Components\Textarea::make('meta')->label('Meta (JSON)')->json(),
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
