<?php

namespace App\Filament\Resources\PaypalConfigResource\Pages;

use App\Filament\Resources\PaypalConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaypalConfig extends EditRecord
{
    protected static string $resource = PaypalConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
