<?php

namespace App\Filament\Resources\BankTransferDetailResource\Pages;

use App\Filament\Resources\BankTransferDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankTransferDetails extends ListRecords
{
    protected static string $resource = BankTransferDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
