<?php

namespace App\Filament\Resources\ClientAccountResource\Pages;

use App\Filament\Resources\ClientAccountResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientAccounts extends ListRecords
{
    protected static string $resource = ClientAccountResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
