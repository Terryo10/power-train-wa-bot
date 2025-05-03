<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaypalConfigResource\Pages;
use App\Models\PaypalConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class PaypalConfigResource extends Resource
{
    protected static ?string $model = PaypalConfig::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Payment Settings';
    protected static ?string $recordTitleAttribute = 'client_id';
    protected static ?string $navigationLabel = 'PayPal Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('client_id')
                            ->required()
                            ->maxLength(255)
                            ->helperText('PayPal API Client ID'),
                        
                        Forms\Components\TextInput::make('client_secret')
                            ->required()
                            ->maxLength(255)
                            ->password()
                            ->helperText('PayPal API Client Secret'),
                        
                        Forms\Components\Toggle::make('sandbox_mode')
                            ->label('Sandbox Mode')
                            ->default(true)
                            ->helperText('Enable for testing mode, disable for live mode'),
                        
                        Forms\Components\TextInput::make('return_url')
                            ->required()
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://yourdomain.com/payments/paypal/return')
                            ->helperText('URL to redirect customers after successful payment'),
                        
                        Forms\Components\TextInput::make('cancel_url')
                            ->required()
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://yourdomain.com/payments/paypal/cancel')
                            ->helperText('URL to redirect customers after cancelled payment'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable or disable PayPal integration'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_id')
                    ->searchable(),
                
                Tables\Columns\IconColumn::make('sandbox_mode')
                    ->boolean()
                    ->label('Sandbox Mode')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (PaypalConfig $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (PaypalConfig $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (PaypalConfig $record) => $record->is_active ? 'danger' : 'success')
                    ->action(function (PaypalConfig $record) {
                        $record->is_active = !$record->is_active;
                        $record->save();
                    }),
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
            'index' => Pages\ListPaypalConfigs::route('/'),
            'create' => Pages\CreatePaypalConfig::route('/create'),
            'edit' => Pages\EditPaypalConfig::route('/{record}/edit'),
        ];
    }
}