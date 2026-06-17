<?php

namespace App\Filament\Resources\SenderIDResource\Pages;

use App\Filament\Resources\SenderIDResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSenderID extends EditRecord
{
    protected static string $resource = SenderIDResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
