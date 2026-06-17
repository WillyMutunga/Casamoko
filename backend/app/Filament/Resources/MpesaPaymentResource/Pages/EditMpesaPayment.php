<?php

namespace App\Filament\Resources\MpesaPaymentResource\Pages;

use App\Filament\Resources\MpesaPaymentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMpesaPayment extends EditRecord
{
    protected static string $resource = MpesaPaymentResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
