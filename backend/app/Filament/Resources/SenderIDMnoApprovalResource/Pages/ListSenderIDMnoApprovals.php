<?php

namespace App\Filament\Resources\SenderIDMnoApprovalResource\Pages;

use App\Filament\Resources\SenderIDMnoApprovalResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSenderIDMnoApprovals extends ListRecords
{
    protected static string $resource = SenderIDMnoApprovalResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
