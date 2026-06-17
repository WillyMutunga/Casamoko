<?php

namespace App\Filament\Resources\IncomingMessageResource\Pages;

use App\Filament\Resources\IncomingMessageResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIncomingMessage extends CreateRecord
{
    protected static string $resource = IncomingMessageResource::class;
}
