<?php

namespace App\Filament\Resources\ContactListResource\Pages;

use App\Filament\Resources\ContactListResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateContactList extends CreateRecord
{
    protected static string $resource = ContactListResource::class;
}
