<?php

namespace App\Filament\Resources\SenderIDMnoApprovalResource\Pages;

use App\Filament\Resources\SenderIDMnoApprovalResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSenderIDMnoApproval extends EditRecord
{
    protected static string $resource = SenderIDMnoApprovalResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
