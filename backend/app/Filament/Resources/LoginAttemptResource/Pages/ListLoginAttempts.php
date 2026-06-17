<?php

namespace App\Filament\Resources\LoginAttemptResource\Pages;

use App\Filament\Resources\LoginAttemptResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoginAttempts extends ListRecords
{
    protected static string $resource = LoginAttemptResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
