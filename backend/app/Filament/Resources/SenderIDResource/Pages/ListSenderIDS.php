<?php

namespace App\Filament\Resources\SenderIDResource\Pages;

use App\Filament\Resources\SenderIDResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSenderIDS extends ListRecords
{
    protected static string $resource = SenderIDResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
