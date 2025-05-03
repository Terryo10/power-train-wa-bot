<?php

namespace App\Filament\Resources\BankTransferDetailResource\Pages;

use App\Filament\Resources\BankTransferDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBankTransferDetail extends EditRecord
{
    protected static string $resource = BankTransferDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
