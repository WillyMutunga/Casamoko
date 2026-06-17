<?php

namespace App\Filament\Resources\CarrierResource\Pages;

use App\Filament\Resources\CarrierResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCarriers extends ListRecords
{
    protected static string $resource = CarrierResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
