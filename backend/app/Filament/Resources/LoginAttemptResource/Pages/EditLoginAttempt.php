<?php

namespace App\Filament\Resources\LoginAttemptResource\Pages;

use App\Filament\Resources\LoginAttemptResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoginAttempt extends EditRecord
{
    protected static string $resource = LoginAttemptResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
