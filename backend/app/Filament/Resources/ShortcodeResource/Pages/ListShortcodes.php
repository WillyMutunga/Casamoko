<?php

namespace App\Filament\Resources\ShortcodeResource\Pages;

use App\Filament\Resources\ShortcodeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShortcodes extends ListRecords
{
    protected static string $resource = ShortcodeResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
