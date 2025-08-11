<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\DB;

use Filament\Tables\Actions\ActionGroup;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Storage;

use App\Models\Inventory;
use App\Models\StoreInventory;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('po_number')
                    ->label('Purhase Order Number')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),

                Forms\Components\Select::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\DatePicker::make('order_date')
                    ->label('Order Date')
                    ->required()
                    ->default(now()),

                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->label('Order Items')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('unit_price')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->minItems(1)
                    ->columns(3),

                Forms\Components\Textarea::make('notes')
                    ->label('Additional Notes')
                    ->maxLength(500),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('echo "# apsprojects_software" >> README.md
git init
git add README.md
git commit -m "first commit"
git branch -M main
git remote add origin https://github.com/Piyush289kumar/apsprojects_software.git
git push -u origin mainpo_number')->label('PO #'),
                Tables\Columns\TextColumn::make('vendor.name')->label('Vendor'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('receive_inventory')
                    ->label('Receive & Update Inventory')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(PurchaseOrder $record) => $record->status !== 'received') // hide if already done
                    ->action(function (PurchaseOrder $record) {
                        DB::transaction(function () use ($record) {
                            // Lock the row for update (prevents double-click race conditions)
                            $record->lockForUpdate();

                            // ✅ Prevent re-receiving
                            if ($record->status === 'received') {
                                throw new \Exception('This purchase order has already been received.');
                            }

                            foreach ($record->items as $item) {
                                $product = $item->product;

                                // 1️⃣ Update central inventory
                                $inventory = Inventory::firstOrCreate(
                                    ['product_id' => $product->id],
                                    ['total_quantity' => 0]
                                );
                                $inventory->increment('total_quantity', $item->quantity);

                                // 2️⃣ Update store inventory
                                $storeInventory = StoreInventory::firstOrCreate(
                                    ['store_id' => $record->store_id, 'product_id' => $product->id],
                                    ['quantity' => 0]
                                );
                                $storeInventory->increment('quantity', $item->quantity);
                            }

                            // 3️⃣ Mark as received
                            $record->update([
                                'status' => 'received',
                                'received_at' => now(),
                            ]);

                            // 4️⃣ Create PDF invoice
                            $pdf = Pdf::loadView('pdf.invoice', ['record' => $record]);
                            $fileName = "invoices/invoice-{$record->id}.pdf";
                            Storage::disk('public')->put($fileName, $pdf->output());

                            $record->update(['invoice_path' => $fileName]);
                        });
                    }),

                Action::make('view_pdf')
                    ->label('View PDF')
                    ->icon('heroicon-o-document-text')
                    ->url(
                        fn(PurchaseOrder $record) => $record->invoice_path
                        ? Storage::disk('public')->url($record->invoice_path)
                        : '#',
                        shouldOpenInNewTab: true
                    )
                    ->visible(fn(PurchaseOrder $record) => !empty($record->invoice_path)),
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
