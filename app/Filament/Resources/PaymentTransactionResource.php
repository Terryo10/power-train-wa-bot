<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentTransactionResource\Pages;
use App\Models\PaymentTransaction;
use App\Services\CashOnDeliveryPaymentService;
use App\Services\BankTransferPaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->required()
                            ->maxLength(255)
                            ->disabled(),
                        
                        Forms\Components\Select::make('order_id')
                            ->relationship('order', 'id')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\Select::make('payment_method_id')
                            ->relationship('paymentMethod', 'name')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('currency')
                            ->required()
                            ->maxLength(10)
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        
                        Forms\Components\TextInput::make('gateway_reference')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('customer_phone')
                            ->tel()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('customer_email')
                            ->email()
                            ->maxLength(255),
                        
                        Forms\Components\Textarea::make('gateway_response')
                            ->columnSpan(2)
                            ->disabled(),
                    ])
                    ->columns(2),
                
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created at')
                            ->content(fn (PaymentTransaction $record): ?string => $record->created_at?->diffForHumans()),
                        
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Last updated at')
                            ->content(fn (PaymentTransaction $record): ?string => $record->updated_at?->diffForHumans()),
                        
                        Forms\Components\Placeholder::make('paid_at')
                            ->label('Paid at')
                            ->content(fn (PaymentTransaction $record): ?string => $record->paid_at?->diffForHumans() ?? 'Not paid'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Payment Method')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'pending',
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'primary' => 'refunded',
                    ]),
                
                Tables\Columns\TextColumn::make('customer_phone')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->relationship('paymentMethod', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('verify_bank_transfer')
                    ->label('Verify Bank Transfer')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (PaymentTransaction $record) {
                        DB::beginTransaction();
                        
                        try {
                            $bankService = new BankTransferPaymentService();
                            $result = $bankService->verifyPayment($record);
                            
                            if ($result['success']) {
                                Notification::make()
                                    ->title('Payment Verified')
                                    ->body("Payment for Order #{$record->order->id} has been verified.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Verification Failed')
                                    ->body($result['message'] ?? 'Failed to verify payment.')
                                    ->danger()
                                    ->send();
                            }
                            
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            
                            Notification::make()
                                ->title('Error')
                                ->body('Failed to verify payment: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (PaymentTransaction $record) => 
                        $record->paymentMethod?->code === 'bank_transfer' && 
                        in_array($record->status, ['pending', 'processing'])
                    ),
                
                Tables\Actions\Action::make('mark_cod_as_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-currency-dollar') 
                    ->color('success')
                    ->action(function (PaymentTransaction $record) {
                        DB::beginTransaction();
                        
                        try {
                            $codService = new CashOnDeliveryPaymentService();
                            $result = $codService->markAsCompleted($record);
                            
                            if ($result['success']) {
                                Notification::make()
                                    ->title('Payment Completed')
                                    ->body("Payment for Order #{$record->order->id} has been marked as completed.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Completion Failed')
                                    ->body($result['message'] ?? 'Failed to mark payment as completed.')
                                    ->danger()
                                    ->send();
                            }
                            
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            
                            Notification::make()
                                ->title('Error')
                                ->body('Failed to mark payment as completed: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (PaymentTransaction $record) => 
                        $record->paymentMethod?->code === 'cod' && 
                        in_array($record->status, ['pending', 'processing'])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListPaymentTransactions::route('/'),
            'view' => Pages\ViewPaymentTransaction::route('/{record}'),
            'edit' => Pages\EditPaymentTransaction::route('/{record}/edit'),
        ];
    }
}