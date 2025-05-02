<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;


class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('phone')
                            ->required()
                            ->tel()
                            ->maxLength(255)
                            ->unique(Driver::class, 'phone', fn ($record) => $record),
                        
                        Forms\Components\Toggle::make('available')
                            ->label('Available for Deliveries')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                
                Tables\Columns\IconColumn::make('available')
                    ->boolean()
                    ->label('Available')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Orders')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('available')
                    ->label('Availability')
                    ->placeholder('All Drivers')
                    ->trueLabel('Available Drivers')
                    ->falseLabel('Unavailable Drivers'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('toggle_availability')
                    ->label(fn (Driver $record) => $record->available ? 'Mark Unavailable' : 'Mark Available')
                    ->icon(fn (Driver $record) => $record->available ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Driver $record) => $record->available ? 'danger' : 'success')
                    ->action(function (Driver $record) {
                        $record->available = !$record->available;
                        $record->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                
                Tables\Actions\BulkAction::make('mark_available')
                    ->label('Mark as Available')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (Builder $query) => $query->update(['available' => true])),
                
                Tables\Actions\BulkAction::make('mark_unavailable')
                    ->label('Mark as Unavailable')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(fn (Builder $query) => $query->update(['available' => false])),
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
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}