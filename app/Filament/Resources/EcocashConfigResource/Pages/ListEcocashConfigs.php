<?php

namespace App\Filament\Resources\EcocashConfigResource\Pages;

use App\Filament\Resources\EcocashConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEcocashConfigs extends ListRecords
{
    protected static string $resource = EcocashConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
