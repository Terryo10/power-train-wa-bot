<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EcocashConfigResource\Pages;
use App\Models\EcocashConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class EcocashConfigResource extends Resource
{
    protected static ?string $model = EcocashConfig::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Payment Settings';
    protected static ?string $recordTitleAttribute = 'integration_id';
    protected static ?string $navigationLabel = 'EcoCash Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('integration_id')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The EcoCash integration ID provided by PayNow'),
                        
                        Forms\Components\TextInput::make('integration_key')
                            ->required()
                            ->maxLength(255)
                            ->password()
                            ->helperText('The EcoCash integration key for API authentication'),
                        
                        Forms\Components\TextInput::make('return_url')
                            ->required()
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://yourdomain.com/payments/ecocash/return')
                            ->helperText('URL to redirect customers after payment'),
                        
                        Forms\Components\TextInput::make('result_url')
                            ->required()
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://yourdomain.com/payments/ecocash/callback')
                            ->helperText('URL for PayNow to send payment notifications'),
                        
                        Forms\Components\TextInput::make('phone_prefix')
                            ->required()
                            ->default('263')
                            ->maxLength(10)
                            ->helperText('Country code prefix for phone numbers (e.g., 263 for Zimbabwe)'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable or disable EcoCash integration'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('integration_id')
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
                    ->label(fn (EcocashConfig $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (EcocashConfig $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (EcocashConfig $record) => $record->is_active ? 'danger' : 'success')
                    ->action(function (EcocashConfig $record) {
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
            'index' => Pages\ListEcocashConfigs::route('/'),
            'create' => Pages\CreateEcocashConfig::route('/create'),
            'edit' => Pages\EditEcocashConfig::route('/{record}/edit'),
        ];
    }
}