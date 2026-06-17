<?php

namespace App\Filament\Resources\MessageRecordResource\Pages;

use App\Filament\Resources\MessageRecordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMessageRecords extends ListRecords
{
    protected static string $resource = MessageRecordResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
