<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankTransferDetailResource\Pages;
use App\Models\BankTransferDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class BankTransferDetailResource extends Resource
{
    protected static ?string $model = BankTransferDetail::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Payment Settings';
    protected static ?string $recordTitleAttribute = 'bank_name';
    protected static ?string $navigationLabel = 'Bank Transfer Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('account_name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('account_number')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('branch_code')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('swift_code')
                            ->maxLength(255),
                        
                        Forms\Components\Textarea::make('instructions')
                            ->maxLength(65535)
                            ->helperText('Additional instructions for customers making bank transfers'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable or disable this bank account for transfers'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank_name')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('account_name')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('account_number')
                    ->searchable(),
                
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
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All Accounts')
                    ->trueLabel('Active Accounts')
                    ->falseLabel('Inactive Accounts'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (BankTransferDetail $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (BankTransferDetail $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (BankTransferDetail $record) => $record->is_active ? 'danger' : 'success')
                    ->action(function (BankTransferDetail $record) {
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
            'index' => Pages\ListBankTransferDetails::route('/'),
            'create' => Pages\CreateBankTransferDetail::route('/create'),
            'edit' => Pages\EditBankTransferDetail::route('/{record}/edit'),
        ];
    }
}