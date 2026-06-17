<?php

namespace App\Filament\Resources\ClientAccountResource\Pages;

use App\Filament\Resources\ClientAccountResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClientAccount extends EditRecord
{
    protected static string $resource = ClientAccountResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
