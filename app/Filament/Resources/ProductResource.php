<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    
    // Updated icon format for Filament v3
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';
    
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Select::make('category')
                            ->options([
                                'load' => 'Truck Load',
                                'building_material' => 'Building Material',
                            ])
                            ->required()
                            ->live(),
                            
                        Forms\Components\TextInput::make('type')
                            ->maxLength(255)
                            ->hidden(fn (Forms\Get $get) => $get('category') === 'load')
                            ->helperText('For building materials: brick, paver, etc.')
                            ->visible(fn (Forms\Get $get) => $get('category') === 'building_material'),
                            
                        Forms\Components\Select::make('variant')
                            ->options([
                                'plain' => 'Plain',
                                'red' => 'Red',
                                'black' => 'Black',
                            ])
                            ->hidden(fn (Forms\Get $get) => $get('category') === 'load')
                            ->visible(fn (Forms\Get $get) => 
                                $get('category') === 'building_material' && 
                                $get('type') === 'paver'
                            )
                            ->live(),
                            
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                            
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'load' => 'Truck Load',
                        'building_material' => 'Building Material',
                        default => $state,
                    })
                    ->color(fn (string $state) => match($state) {
                        'load' => 'primary',
                        'building_material' => 'success',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('type')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('N/A'),
                    
                Tables\Columns\TextColumn::make('variant')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('N/A'),
                    
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'load' => 'Truck Load',
                        'building_material' => 'Building Material',
                    ]),
                    
                Tables\Filters\SelectFilter::make('type')
                    ->options(function () {
                        return Product::distinct()->pluck('type', 'type')
                            ->filter()
                            ->toArray();
                    }),
                
                Tables\Filters\SelectFilter::make('variant')
                    ->options([
                        'plain' => 'Plain',
                        'red' => 'Red',
                        'black' => 'Black',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('update_price')
                        ->label('Update Price')
                        ->icon('heroicon-o-currency-dollar')
                        ->form([
                            Forms\Components\Radio::make('price_action')
                                ->label('Price Action')
                                ->options([
                                    'set' => 'Set to specific amount',
                                    'increase' => 'Increase by percentage',
                                    'decrease' => 'Decrease by percentage',
                                ])
                                ->default('set')
                                ->required(),
                                
                            Forms\Components\TextInput::make('value')
                                ->label('Value')
                                ->numeric()
                                ->required()
                                ->helperText(function (Forms\Get $get) {
                                    if ($get('price_action') === 'set') {
                                        return 'Enter the new price in dollars';
                                    }
                                    return 'Enter percentage value (e.g. 10 for 10%)';
                                }),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                switch ($data['price_action']) {
                                    case 'set':
                                        $record->price = $data['value'];
                                        break;
                                    case 'increase':
                                        $record->price = $record->price * (1 + $data['value'] / 100);
                                        break;
                                    case 'decrease':
                                        $record->price = $record->price * (1 - $data['value'] / 100);
                                        break;
                                }
                                $record->save();
                            }
                        }),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('name');
    }
}