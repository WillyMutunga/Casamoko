<?php

namespace App\Filament\Resources\IncomingMessageResource\Pages;

use App\Filament\Resources\IncomingMessageResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIncomingMessage extends EditRecord
{
    protected static string $resource = IncomingMessageResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
