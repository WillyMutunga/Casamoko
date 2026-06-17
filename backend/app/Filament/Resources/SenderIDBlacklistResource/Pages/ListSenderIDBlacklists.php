<?php

namespace App\Filament\Resources\SenderIDBlacklistResource\Pages;

use App\Filament\Resources\SenderIDBlacklistResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSenderIDBlacklists extends ListRecords
{
    protected static string $resource = SenderIDBlacklistResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
