<?php

namespace App\Filament\Resources\PaymentTransactionResource\Pages;

use App\Filament\Resources\PaymentTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentTransaction extends ViewRecord
{
    protected static string $resource = PaymentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
