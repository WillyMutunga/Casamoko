<?php

namespace App\Filament\Resources\ResellerAccountResource\Pages;

use App\Filament\Resources\ResellerAccountResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResellerAccount extends EditRecord
{
    protected static string $resource = ResellerAccountResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
