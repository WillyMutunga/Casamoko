<?php

namespace App\Filament\Resources\OptOutRegistryResource\Pages;

use App\Filament\Resources\OptOutRegistryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOptOutRegistry extends EditRecord
{
    protected static string $resource = OptOutRegistryResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
