<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Driver;
use App\Models\Order;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Form $form): \Filament\Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_phone')
                            ->required()
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('delivery_address')
                            ->required()
                            ->maxLength(65535),

                        Forms\Components\Select::make('status')
                            ->options([
                                Order::STATUS_PENDING => 'Pending',
                                Order::STATUS_IN_PROGRESS => 'In Progress',
                                Order::STATUS_DELIVERED => 'Delivered',
                            ])
                            ->required(),

                        Forms\Components\Select::make('driver_id')
                            ->label('Driver')
                            ->options(function () {
                                return Driver::available()->pluck('name', 'id');
                            })
                            ->searchable(),
                    ])
                    ->columns(2),

                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created at')
                            ->content(fn(Order $record): ?string => $record->created_at?->diffForHumans()),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Last updated at')
                            ->content(fn(Order $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Order::STATUS_PENDING,
                        'primary' => Order::STATUS_IN_PROGRESS,
                        'success' => Order::STATUS_DELIVERED,
                    ]),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->placeholder('Unassigned'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Order::STATUS_PENDING => 'Pending',
                        Order::STATUS_IN_PROGRESS => 'In Progress',
                        Order::STATUS_DELIVERED => 'Delivered',
                    ]),

                Tables\Filters\SelectFilter::make('driver')
                    ->relationship('driver', 'name')
                    ->label('Driver'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Add this to app/Filament/Resources/OrderResource.php in the assign_driver action
                Tables\Actions\Action::make('assign_driver')
                    ->label('Assign Driver')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('driver_id')
                            ->label('Select Driver')
                            ->options(function () {
                                return Driver::available()->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data, WhatsAppService $whatsAppService) {
                        DB::beginTransaction();

                        try {
                            $driver = Driver::findOrFail($data['driver_id']);

                            // Log the attempt
                            Log::info('Assigning driver to order', [
                                'order_id' => $record->id,
                                'driver_id' => $driver->id,
                                'driver_name' => $driver->name,
                                'driver_phone' => $driver->phone
                            ]);

                            // Update order
                            $record->driver_id = $driver->id;
                            $record->status = Order::STATUS_IN_PROGRESS;
                            $record->save();

                            // Send WhatsApp notification to the driver
                            try {
                                $whatsAppService->notifyDriver($driver, $record);

                                // Show success notification
                                Notification::make()
                                    ->title('Driver Assigned')
                                    ->body("Order #{$record->id} has been assigned to {$driver->name}. WhatsApp notification sent.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                // If WhatsApp notification fails, still proceed with assignment
                                Log::error('Failed to send WhatsApp notification to driver', [
                                    'error' => $e->getMessage(),
                                    'driver_id' => $driver->id,
                                    'driver_phone' => $driver->phone
                                ]);

                                Notification::make()
                                    ->title('Driver Assigned')
                                    ->body("Order #{$record->id} has been assigned to {$driver->name}. Warning: WhatsApp notification failed.")
                                    ->warning()
                                    ->send();
                            }

                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();

                            Log::error('Failed to assign driver', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);

                            Notification::make()
                                ->title('Error')
                                ->body('Failed to assign driver: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn(Order $record) =>
                        $record->status === Order::STATUS_PENDING && $record->driver_id === null
                    )

            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('items')
            ->latest();
    }
}
