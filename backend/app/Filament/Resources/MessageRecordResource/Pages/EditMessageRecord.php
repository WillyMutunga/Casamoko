<?php

namespace App\Filament\Resources\MessageRecordResource\Pages;

use App\Filament\Resources\MessageRecordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMessageRecord extends EditRecord
{
    protected static string $resource = MessageRecordResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
