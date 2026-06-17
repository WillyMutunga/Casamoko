<?php

namespace App\Filament\Resources\LoginAttemptResource\Pages;

use App\Filament\Resources\LoginAttemptResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLoginAttempt extends CreateRecord
{
    protected static string $resource = LoginAttemptResource::class;
}
