<?php

namespace App\Filament\Resources\ContactListResource\Pages;

use App\Filament\Resources\ContactListResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactList extends EditRecord
{
    protected static string $resource = ContactListResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
