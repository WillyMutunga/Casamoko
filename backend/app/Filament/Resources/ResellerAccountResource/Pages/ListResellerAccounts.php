<?php

namespace App\Filament\Resources\ResellerAccountResource\Pages;

use App\Filament\Resources\ResellerAccountResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResellerAccounts extends ListRecords
{
    protected static string $resource = ResellerAccountResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
