<?php

namespace App\Filament\Resources\EcocashConfigResource\Pages;

use App\Filament\Resources\EcocashConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEcocashConfig extends EditRecord
{
    protected static string $resource = EcocashConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
