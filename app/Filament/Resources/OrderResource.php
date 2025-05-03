<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Driver;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
                        
                        Forms\Components\Select::make('payment_status')
                            ->options([
                                Order::PAYMENT_STATUS_UNPAID => 'Unpaid',
                                Order::PAYMENT_STATUS_PARTIALLY_PAID => 'Partially Paid',
                                Order::PAYMENT_STATUS_PAID => 'Paid',
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
                            ->content(fn (Order $record): ?string => $record->created_at?->diffForHumans()),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Last updated at')
                            ->content(fn (Order $record): ?string => $record->updated_at?->diffForHumans()),
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
                
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'danger' => Order::PAYMENT_STATUS_UNPAID,
                        'warning' => Order::PAYMENT_STATUS_PARTIALLY_PAID,
                        'success' => Order::PAYMENT_STATUS_PAID,
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
                
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        Order::PAYMENT_STATUS_UNPAID => 'Unpaid',
                        Order::PAYMENT_STATUS_PARTIALLY_PAID => 'Partially Paid',
                        Order::PAYMENT_STATUS_PAID => 'Paid',
                    ]),
                
                Tables\Filters\SelectFilter::make('driver')
                    ->relationship('driver', 'name')
                    ->label('Driver'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
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
                            
                            // Update order
                            $record->driver_id = $driver->id;
                            $record->status = Order::STATUS_IN_PROGRESS;
                            $record->save();
                            
                            // Send WhatsApp notification to the driver
                            $whatsAppService->notifyDriver($driver, $record);
                            
                            // Show success notification
                            Notification::make()
                                ->title('Driver Assigned')
                                ->body("Order #{$record->id} has been assigned to {$driver->name}")
                                ->success()
                                ->send();
                            
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            
                            Notification::make()
                                ->title('Error')
                                ->body('Failed to assign driver: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Order $record) => 
                        $record->status === Order::STATUS_PENDING && $record->driver_id === null
                    ),
                
                Tables\Actions\Action::make('process_payment')
                    ->label('Process Payment')
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('payment_method_id')
                            ->label('Payment Method')
                            ->options(function () {
                                return PaymentMethod::active()->pluck('name', 'id');
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $paymentMethod = PaymentMethod::find($state);
                                if ($paymentMethod) {
                                    $set('payment_method_code', $paymentMethod->code);
                                }
                            }),
                        
                        Forms\Components\Hidden::make('payment_method_code'),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        
                        // EcoCash specific fields
                        Forms\Components\TextInput::make('phone_number')
                            ->label('EcoCash Phone Number')
                            ->tel()
                            ->visible(fn (callable $get) => $get('payment_method_code') === 'ecocash'),
                        
                        // PayPal specific fields
                        Forms\Components\TextInput::make('email')
                            ->label('PayPal Email')
                            ->email()
                            ->visible(fn (callable $get) => $get('payment_method_code') === 'paypal'),
                    ])
                    ->action(function (Order $record, array $data) {
                        DB::beginTransaction();
                        
                        try {
                            $paymentMethod = PaymentMethod::findOrFail($data['payment_method_id']);
                            $amount = floatval($data['amount']);
                            
                            // Create a payment transaction based on method
                            switch ($paymentMethod->code) {
                                case 'ecocash':
                                    // Redirect to EcoCash payment page
                                    redirect()->route('payments.ecocash.process', [
                                        'orderId' => $record->id,
                                        'phone_number' => $data['phone_number'],
                                        'amount' => $amount,
                                    ]);
                                    break;
                                
                                case 'paypal':
                                    // Redirect to PayPal payment page
                                    redirect()->route('payments.paypal.process', [
                                        'orderId' => $record->id,
                                        'email' => $data['email'],
                                        'amount' => $amount,
                                    ]);
                                    break;
                                
                                case 'cod':
                                    $service = new \App\Services\CashOnDeliveryPaymentService();
                                    $result = $service->createCodPayment($record);
                                    
                                    if ($result['success']) {
                                        Notification::make()
                                            ->title('COD Payment Created')
                                            ->body("Order #{$record->id} has been set up for Cash on Delivery payment.")
                                            ->success()
                                            ->send();
                                    } else {
                                        throw new \Exception($result['message'] ?? 'Failed to set up COD payment.');
                                    }
                                    break;
                                
                                case 'bank_transfer':
                                    $service = new \App\Services\BankTransferPaymentService();
                                    $result = $service->createBankTransferPayment($record);
                                    
                                    if ($result['success']) {
                                        Notification::make()
                                            ->title('Bank Transfer Created')
                                            ->body("Bank transfer instructions have been sent to the customer.")
                                            ->success()
                                            ->send();
                                    } else {
                                        throw new \Exception($result['message'] ?? 'Failed to set up bank transfer.');
                                    }
                                    break;
                                
                                default:
                                    throw new \Exception('Unsupported payment method: ' . $paymentMethod->code);
                            }
                            
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            
                            Notification::make()
                                ->title('Payment Error')
                                ->body('Failed to process payment: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Order $record) => 
                        $record->payment_status !== Order::PAYMENT_STATUS_PAID
                    ),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            
            // Add a new relation manager for payment transactions
            RelationManagers\PaymentTransactionsRelationManager::class,
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